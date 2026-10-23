<?php

namespace App\Http\Controllers;

use App\Models\Ministry;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\TenantAudit;
use App\Models\User;
use App\Services\TenantAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TenantAdminController extends Controller
{
    public function __construct(private TenantAuditLogger $audit) {}

    public function settings(Request $request) { return response()->json(['data' => $request->attributes->get('organisation')]); }

    public function updateSettings(Request $request)
    {
        $organisation = $request->attributes->get('organisation');
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'], 'branding' => ['sometimes', 'array'],
            'branding.name' => ['nullable', 'string', 'max:150'],
            'branding.tagline' => ['nullable', 'string', 'max:255'],
            'branding.description' => ['nullable', 'string', 'max:1000'],
            'branding.subtitle' => ['nullable', 'string', 'max:150'],
            'branding.footer_text' => ['nullable', 'string', 'max:255'],
            'branding.logo_url' => ['nullable', 'url', 'max:2048'], 'branding.favicon_url' => ['nullable', 'url', 'max:2048'],
            'branding.thumbnail_url' => ['nullable', 'url', 'max:2048'], 'branding.banner_url' => ['nullable', 'url', 'max:2048'],
            'branding.rumble_url' => ['nullable', 'url', 'max:2048'],
            'branding.primary_colour' => ['nullable', 'regex:/^#[0-9a-f]{6}$/i'],
            'branding.secondary_colour' => ['nullable', 'regex:/^#[0-9a-f]{6}$/i'],
            'branding.font_family' => ['nullable', 'string', 'max:100'],
            'branding.social_links' => ['nullable', 'array'],
            'branding.social_links.*' => ['nullable', 'url', 'max:2048'],
            'branding.contact' => ['nullable', 'array'], 'branding.navigation' => ['nullable', 'array'],
            'branding.legal_links' => ['nullable', 'array'], 'settings' => ['sometimes', 'array'],
            'settings.locale' => ['nullable', 'string', 'max:12'], 'settings.timezone' => ['nullable', 'timezone'],
            'settings.description' => ['nullable', 'string', 'max:1000'],
            'settings.email_sender_name' => ['nullable', 'string', 'max:150'], 'settings.email_sender_address' => ['nullable', 'email'],
        ]);
        $before = $organisation->toArray();
        $organisation->update($data);
        $this->audit->log($request, 'settings.updated', $organisation, $before, $organisation->fresh()->toArray());
        return response()->json(['data' => $organisation->fresh()]);
    }

    public function updateModules(Request $request)
    {
        $organisation = $request->attributes->get('organisation');
        $data = $request->validate(['modules' => ['required', 'array'], 'modules.*' => ['boolean']]);
        $unknown = array_diff(array_keys($data['modules']), array_keys(Organisation::DEFAULT_MODULES));
        abort_if($unknown, 422, 'Unknown modules: '.implode(', ', $unknown));
        $before = $organisation->modules;
        $organisation->update(['modules' => array_merge(Organisation::DEFAULT_MODULES, $data['modules'])]);
        $this->audit->log($request, 'modules.updated', $organisation, ['modules' => $before], ['modules' => $organisation->modules]);
        return response()->json(['modules' => $organisation->modules]);
    }

    public function ministries(Request $request) { return response()->json(['data' => Ministry::query()->orderBy('name')->get()]); }

    public function storeMinistry(Request $request)
    {
        $data = $this->ministryData($request);
        $ministry = Ministry::create(array_merge($data, ['entity' => $request->attributes->get('organisation')->slug]));
        $this->audit->log($request, 'ministry.created', $ministry, null, $ministry->toArray());
        return response()->json(['data' => $ministry], 201);
    }

    public function updateMinistry(Request $request, Ministry $ministry)
    {
        $before = $ministry->toArray();
        $ministry->update($this->ministryData($request, $ministry));
        $this->audit->log($request, 'ministry.updated', $ministry, $before, $ministry->toArray());
        return response()->json(['data' => $ministry]);
    }

    public function users(Request $request)
    {
        $organisation = $request->attributes->get('organisation');
        return response()->json(['data' => $organisation->memberships()->with('user:id,name,email')->orderBy('role')->paginate(100)]);
    }

    public function storeUser(Request $request)
    {
        $organisation = $request->attributes->get('organisation');
        $data = $request->validate([
            'name' => ['required_without:user_id', 'string', 'max:150'], 'email' => ['required_without:user_id', 'email', 'max:255'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'], 'role' => ['required', Rule::in(['owner', 'admin', 'user'])],
        ]);
        if ($data['role'] === 'owner') $this->authorizeOwner($request);
        [$membership, $created] = DB::transaction(function () use ($organisation, $data) {
            $user = isset($data['user_id']) ? User::findOrFail($data['user_id']) : User::firstOrCreate(
                ['email' => strtolower($data['email'])],
                ['name' => $data['name'], 'password' => Hash::make(Str::random(40)), 'is_superadmin' => false]
            );
            $membership = OrganisationMembership::updateOrCreate(
                ['organisation_id' => $organisation->id, 'user_id' => $user->id],
                ['role' => $data['role'], 'status' => 'active']
            );
            return [$membership->load('user:id,name,email'), $user->wasRecentlyCreated];
        });
        $this->audit->log($request, 'membership.created', $membership, null, $membership->toArray());
        return response()->json(['data' => $membership, 'created_user' => $created], 201);
    }

    public function updateUser(Request $request, OrganisationMembership $membership)
    {
        $organisation = $request->attributes->get('organisation');
        abort_unless($membership->organisation_id === $organisation->id, 404);
        $data = $request->validate(['role' => ['sometimes', Rule::in(['owner', 'admin', 'user'])], 'status' => ['sometimes', Rule::in(['active', 'suspended'])]]);
        if ($membership->role === 'owner' || ($data['role'] ?? null) === 'owner') $this->authorizeOwner($request);
        $this->protectLastOwner($membership, $data);
        $before = $membership->toArray();
        $membership->update($data);
        $this->audit->log($request, 'membership.updated', $membership, $before, $membership->toArray());
        return response()->json(['data' => $membership->load('user:id,name,email')]);
    }

    public function destroyUser(Request $request, OrganisationMembership $membership)
    {
        abort_unless($membership->organisation_id === $request->attributes->get('organisation')->id, 404);
        if ($membership->role === 'owner') $this->authorizeOwner($request);
        $this->protectLastOwner($membership, ['status' => 'removed']);
        $before = $membership->toArray();
        $membership->delete();
        $this->audit->log($request, 'membership.removed', $membership, $before, null);
        return response()->noContent();
    }

    public function audits(Request $request)
    {
        $id = $request->attributes->get('organisation')->id;
        return response()->json(['data' => TenantAudit::where('organisation_id', $id)->latest('created_at')->paginate(100)]);
    }

    private function authorizeOwner(Request $request): void
    {
        if ($request->user()->is_superadmin) return;
        abort_unless($request->user()->organisationMemberships()->where('organisation_id', $request->attributes->get('organisation')->id)
            ->where('role', 'owner')->where('status', 'active')->exists(), 403);
    }

    private function protectLastOwner(OrganisationMembership $membership, array $changes): void
    {
        if ($membership->role !== 'owner' || (($changes['role'] ?? 'owner') === 'owner' && ($changes['status'] ?? 'active') === 'active')) return;
        abort_if(OrganisationMembership::where('organisation_id', $membership->organisation_id)->where('role', 'owner')->where('status', 'active')->count() <= 1, 422, 'The last active owner cannot be removed.');
    }

    private function ministryData(Request $request, ?Ministry $ministry = null): array
    {
        return $request->validate([
            'slug' => [$ministry ? 'sometimes' : 'required', 'alpha_dash', 'max:100', Rule::unique('ministries')->where('organisation_id', $request->attributes->get('organisation')->id)->ignore($ministry)],
            'name' => [$ministry ? 'sometimes' : 'required', 'string', 'max:150'], 'description' => ['nullable', 'string'],
            'whatsapp_number' => ['nullable', 'string', 'max:30'], 'details' => ['nullable', 'array'], 'theme' => ['nullable', 'array'],
        ]);
    }
}
