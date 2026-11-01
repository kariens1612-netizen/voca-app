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

    public function completeSetup(Request $request)
    {
        $this->authorizeOwner($request);
        $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'branding' => ['required', 'array'],
            'branding.name' => ['required', 'string', 'max:150'],
            'branding.description' => ['required', 'string', 'max:1000'],
            'branding.contact.email' => ['required', 'email', 'max:255'],
            'branding.contact.phone' => ['nullable', 'string', 'max:50'],
            'branding.contact.address' => ['nullable', 'string', 'max:500'],
            'settings.timezone' => ['required', 'timezone'],
            'first_ministry' => ['nullable', 'array'],
            'first_ministry.name' => ['required_with:first_ministry', 'string', 'max:150'],
            'first_ministry.slug' => ['required_with:first_ministry', 'alpha_dash', 'max:100'],
        ]);
        return DB::transaction(function () use ($request) {
            $organisation = Organisation::whereKey($request->attributes->get('organisation')->id)->lockForUpdate()->firstOrFail();
            abort_if(data_get($organisation->settings, 'onboarding.completed_at'), 409, 'Setup is already complete. Use the dashboard to make further changes.');
            $request->attributes->set('organisation', $organisation);
            // Reuse the same validation and audit trail as the existing dashboard.
            $request->merge([
                'branding' => array_replace_recursive($organisation->branding ?? [], $request->input('branding')),
                'settings' => array_replace_recursive($organisation->settings ?? [], $request->input('settings', [])),
            ]);
            $this->updateSettings($request);
            $this->updateModules($request);
            if ($ministry = $request->input('first_ministry')) {
                $request->merge($ministry);
                $this->storeMinistry($request);
            }
            $organisation->refresh();
            $organisation->update(['settings' => array_merge($organisation->settings ?? [], [
                'onboarding' => ['completed_at' => now()->toIso8601String(), 'completed_by' => $request->user()->id],
            ])]);
            return response()->json(['data' => $organisation->fresh()]);
        });
    }

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
            abort_if($user->is_superadmin, 403, 'Platform accounts cannot be added here.');
            if (isset($data['user_id'])) abort_unless($user->canAccessOrganisation($organisation->id), 404);
            $membership = OrganisationMembership::firstOrCreate(
                ['organisation_id' => $organisation->id, 'user_id' => $user->id],
                ['role' => $data['role'], 'status' => 'active']
            );
            return [$membership->load('user:id,name,email'), $user->wasRecentlyCreated];
        });
        abort_unless($membership->status === 'active', 422, 'Restore access before sending an invitation.');
        $invitationStatus = app(\App\Services\AccountInvitation::class)->send(User::findOrFail($membership->user_id), $organisation);
        $this->audit->log($request, 'membership.created', $membership, null, $membership->toArray());
        return response()->json(['data' => $membership, 'created_user' => $created, 'invitation_sent' => $invitationStatus === \Illuminate\Support\Facades\Password::RESET_LINK_SENT], 201);
    }

    public function updateUser(Request $request, OrganisationMembership $membership)
    {
        return DB::transaction(function () use ($request, $membership) {
            Organisation::whereKey($request->attributes->get('organisation')->id)->lockForUpdate()->firstOrFail();
            $membership->refresh();
            $organisation = $request->attributes->get('organisation');
            abort_unless($membership->organisation_id === $organisation->id, 404);
            $data = $request->validate(['role' => ['sometimes', Rule::in(['owner', 'admin', 'user'])], 'status' => ['sometimes', Rule::in(['active', 'suspended'])]]);
            if ($membership->role === 'owner' || ($data['role'] ?? null) === 'owner') $this->authorizeOwner($request);
            $this->protectLastOwner($membership, $data);
            $before = $membership->toArray();
            $membership->update($data);
            $this->audit->log($request, 'membership.updated', $membership, $before, $membership->toArray());
            return response()->json(['data' => $membership->load('user:id,name,email')]);
        });
    }

    public function destroyUser(Request $request, OrganisationMembership $membership)
    {
        return DB::transaction(function () use ($request, $membership) {
            Organisation::whereKey($request->attributes->get('organisation')->id)->lockForUpdate()->firstOrFail();
            $membership->refresh();
            abort_unless($membership->organisation_id === $request->attributes->get('organisation')->id, 404);
            if ($membership->role === 'owner') $this->authorizeOwner($request);
            $this->protectLastOwner($membership, ['status' => 'removed']);
            $before = $membership->toArray();
            DB::transaction(function () use ($membership) {
                \App\Models\MinistryMembership::withoutGlobalScopes()->where('organisation_id', $membership->organisation_id)->where('user_id', $membership->user_id)->delete();
                $membership->delete();
            });
            $this->audit->log($request, 'membership.removed', $membership, $before, null);
            return response()->noContent();
        });
    }

    public function invite(Request $request, OrganisationMembership $membership)
    {
        abort_unless($membership->organisation_id === $request->attributes->get('organisation')->id, 404);
        abort_unless($membership->status === 'active', 422, 'Restore access before sending an invitation.');
        if ($membership->role === 'owner') $this->authorizeOwner($request);
        $status = app(\App\Services\AccountInvitation::class)->send($membership->user, $request->attributes->get('organisation'));
        return response()->json(['message' => $status === \Illuminate\Support\Facades\Password::RESET_LINK_SENT ? 'Invitation sent.' : 'A link was sent recently. Please wait before resending.']);
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
