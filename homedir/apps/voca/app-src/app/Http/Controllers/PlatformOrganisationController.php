<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\OrganisationDomain;
use App\Models\OrganisationMembership;
use App\Models\TenantAudit;
use App\Models\User;
use App\Services\TenantAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlatformOrganisationController extends Controller
{
    public function __construct(private TenantAuditLogger $audit) {}

    private function authorizePlatform(Request $request): void { abort_unless($request->user()?->is_superadmin, 403); }

    public function index(Request $request)
    {
        $this->authorizePlatform($request);
        return response()->json(['data' => Organisation::with(['domains', 'memberships.user:id,name,email'])
            ->withCount(['ministries', 'users'])->orderBy('name')->paginate(50)]);
    }

    public function store(Request $request)
    {
        $this->authorizePlatform($request);
        $data = $this->validated($request);
        $organisation = DB::transaction(function () use ($request, $data) {
            $organisation = Organisation::create(array_merge([
                'status' => 'active', 'plan' => 'standard',
                'modules' => Organisation::DEFAULT_MODULES,
                'settings' => ['locale' => 'en', 'timezone' => 'Africa/Johannesburg'],
            ], $data));
            if ($request->filled('owner_user_id')) OrganisationMembership::create([
                'organisation_id' => $organisation->id, 'user_id' => $request->integer('owner_user_id'),
                'role' => 'owner', 'status' => 'active',
            ]);
            return $organisation;
        });
        $this->audit->log($request, 'organisation.created', $organisation, null, $organisation->toArray());
        return response()->json(['data' => $organisation->load('domains')], 201);
    }

    public function update(Request $request, Organisation $organisation)
    {
        $this->authorizePlatform($request);
        $before = $organisation->toArray();
        $organisation->update($this->validated($request, $organisation));
        $this->audit->log($request, 'organisation.updated', $organisation, $before, $organisation->fresh()->toArray());
        return response()->json(['data' => $organisation->fresh('domains')]);
    }

    public function destroy(Request $request, Organisation $organisation)
    {
        $this->authorizePlatform($request);
        abort_if($organisation->slug === config('tenancy.default_slug'), 422, 'The default organisation cannot be archived.');
        $before = $organisation->toArray();
        $organisation->update(['status' => 'archived', 'archived_at' => now()]);
        $this->audit->log($request, 'organisation.archived', $organisation, $before, $organisation->toArray());
        return response()->noContent();
    }

    public function domain(Request $request, Organisation $organisation)
    {
        $this->authorizePlatform($request);
        $data = $request->validate(['host' => ['required', 'string', 'max:253', 'regex:/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', 'unique:organisation_domains,host'], 'is_primary' => ['sometimes', 'boolean']]);
        $domain = $organisation->domains()->create([
            'host' => strtolower($data['host']), 'is_primary' => $data['is_primary'] ?? false,
            'verification_token' => Str::random(48),
        ]);
        $this->audit->log($request, 'domain.created', $domain, null, $domain->toArray());
        return response()->json(['data' => $domain, 'verification' => ['type' => 'TXT', 'name' => '_voca-verify.'.$domain->host, 'value' => $domain->verification_token]], 201);
    }

    public function verifyDomain(Request $request, Organisation $organisation, OrganisationDomain $domain)
    {
        $this->authorizePlatform($request);
        abort_unless($domain->organisation_id === $organisation->id, 404);
        // DNS verification is deliberately confirmed by the platform operator after checking the shown TXT value.
        $domain->update(['verified_at' => now()]);
        $this->audit->log($request, 'domain.verified', $domain, null, ['host' => $domain->host]);
        return response()->json(['data' => $domain]);
    }

    public function impersonate(Request $request, Organisation $organisation, User $user)
    {
        $this->authorizePlatform($request);
        abort_unless($user->organisationMemberships()->where('organisation_id', $organisation->id)->where('status', 'active')->exists(), 422);
        $this->audit->log($request, 'impersonation.started', $user, null, ['organisation_id' => $organisation->id]);
        $request->session()->put('impersonator_id', $request->user()->id);
        $request->session()->put('impersonated_organisation_id', $organisation->id);
        auth()->login($user);
        $request->session()->regenerate();
        return response()->json(['message' => 'Impersonation started.']);
    }

    public function storeUser(Request $request, Organisation $organisation)
    {
        $this->authorizePlatform($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'role' => ['required', Rule::in(['owner', 'admin', 'user'])],
        ]);
        $membership = DB::transaction(function () use ($organisation, $data) {
            $user = User::firstOrCreate(['email' => strtolower($data['email'])], [
                'name' => $data['name'], 'password' => Hash::make(Str::random(40)), 'is_superadmin' => false,
            ]);
            return OrganisationMembership::updateOrCreate(
                ['organisation_id' => $organisation->id, 'user_id' => $user->id],
                ['role' => $data['role'], 'status' => 'active']
            );
        });
        $this->audit->log($request, 'organisation.user_added', $membership, null, $membership->toArray());
        return response()->json(['data' => $membership->load('user:id,name,email')], 201);
    }

    public function updateUser(Request $request, Organisation $organisation, OrganisationMembership $membership)
    {
        $this->authorizePlatform($request);
        abort_unless($membership->organisation_id === $organisation->id, 404);
        $data = $request->validate(['role' => ['sometimes', Rule::in(['owner', 'admin', 'user'])], 'status' => ['sometimes', Rule::in(['active', 'suspended'])]]);
        $before = $membership->toArray();
        $membership->update($data);
        $this->audit->log($request, 'organisation.user_updated', $membership, $before, $membership->toArray());
        return response()->json(['data' => $membership->load('user:id,name,email')]);
    }

    public function destroyUser(Request $request, Organisation $organisation, OrganisationMembership $membership)
    {
        $this->authorizePlatform($request);
        abort_unless($membership->organisation_id === $organisation->id, 404);
        abort_if($membership->role === 'owner' && $membership->status === 'active'
            && $organisation->memberships()->where('role', 'owner')->where('status', 'active')->count() <= 1,
            422, 'The last active owner cannot be removed.');
        $before = $membership->toArray();
        $membership->delete();
        $this->audit->log($request, 'organisation.user_removed', $organisation, $before, null);
        return response()->noContent();
    }

    public function stopImpersonating(Request $request)
    {
        $id = $request->session()->pull('impersonator_id');
        abort_unless($id, 422);
        $request->session()->forget('impersonated_organisation_id');
        auth()->loginUsingId($id);
        $request->session()->regenerate();
        return response()->json(['message' => 'Impersonation stopped.']);
    }

    public function audits(Request $request)
    {
        $this->authorizePlatform($request);
        return response()->json(['data' => TenantAudit::query()->latest('created_at')->paginate(100)]);
    }

    private function validated(Request $request, ?Organisation $organisation = null): array
    {
        return $request->validate([
            'name' => [$organisation ? 'sometimes' : 'required', 'string', 'max:150'],
            'slug' => [$organisation ? 'sometimes' : 'required', 'alpha_dash', 'max:80', Rule::unique('organisations')->ignore($organisation)],
            'status' => ['sometimes', Rule::in(['active', 'suspended', 'archived'])],
            'plan' => ['sometimes', 'string', 'max:40'], 'storage_limit_bytes' => ['sometimes', 'integer', 'min:0'],
            'branding' => ['sometimes', 'array'], 'settings' => ['sometimes', 'array'], 'modules' => ['sometimes', 'array'],
            'owner_user_id' => ['sometimes', 'integer', 'exists:users,id'],
        ]);
    }
}
