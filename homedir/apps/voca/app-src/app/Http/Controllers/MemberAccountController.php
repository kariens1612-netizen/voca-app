<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\OrganisationMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Http\Client\ConnectionException;

class MemberAccountController extends AuthController
{
    public function login(Request $request) { return $this->authenticateMember($request, false); }
    public function register(Request $request) { return $this->authenticateMember($request, true); }

    public function forgot(Request $request) { return $this->recoverMember($request, false); }
    public function reset(Request $request) { return $this->recoverMember($request, true); }

    public function dashboard(Request $request)
    {
        abort_unless($request->attributes->get('organisation')?->slug === 'voca', 404);
        $user = $request->user();
        $token = $request->session()->get('voca_member_token');
        abort_unless($user?->website_member_id && is_string($token), 401, 'Sign in again with your VOCA member account to open your dashboard.');
        abort_unless($user->canAccessOrganisation($request->attributes->get('organisation')?->id), 403);
        $url = rtrim((string) config('services.voca_members.url'), '/');
        $key = config('services.voca_members.key');
        abort_unless(is_string($key) && strlen($key) >= 32 && (str_starts_with($url, 'https://') || (app()->environment(['local', 'testing']) && str_starts_with($url, 'http://'))), 503);
        try {
            $response = Http::acceptJson()->withHeaders(['X-Voca-Member-Key' => $key])->withToken($token)
                ->connectTimeout(5)->timeout(15)->withOptions(['allow_redirects' => false])
                ->post($url.'/api/mobile-members/dashboard-link');
        } catch (ConnectionException $exception) {
            abort(503, 'Your dashboard is temporarily unavailable. Please try again.');
        }
        if ($response->status() === 401) {
            $request->session()->forget('voca_member_token');
            abort(401, 'Sign in again to open your dashboard.');
        }
        abort_unless($response->successful(), 503, 'Your dashboard is temporarily unavailable. Please try again.');
        $target = $response->json('url');
        // Only the configured website can receive this one-time credential.
        abort_unless(is_string($target) && str_starts_with($target, $url.'/member-dashboard/open?'), 503);
        return response()->json(['url' => $target])->header('Cache-Control', 'no-store');
    }

    private function recoverMember(Request $request, bool $reset)
    {
        abort_unless($request->attributes->get('organisation')?->slug === 'voca', 404);
        $data = $request->validate(array_merge(['email' => ['required', 'email']], $reset ? [
            'password_confirmation' => ['required', 'string'],
            'token' => ['required', 'string'], 'password' => ['required', 'string', 'min:12', 'max:1024', 'confirmed'],
        ] : []));
        $url = rtrim((string) config('services.voca_members.url'), '/');
        $key = config('services.voca_members.key');
        abort_unless(is_string($key) && strlen($key) >= 32 && (str_starts_with($url, 'https://') || (app()->environment(['local', 'testing']) && str_starts_with($url, 'http://'))), 503);
        $data['email'] = strtolower(trim($data['email']));
        try {
            $response = Http::acceptJson()->withHeaders(['X-Voca-Member-Key' => $key])->connectTimeout(5)->timeout(15)->withOptions(['allow_redirects' => false])
                ->post($url.'/api/mobile-members/'.($reset ? 'reset-password' : 'forgot-password'), $data);
        } catch (ConnectionException $e) { abort(503, 'Member accounts are temporarily unavailable.'); }
        if ($response->status() === 429) return response()->json(['message' => 'Too many attempts. Please try again later.'], 429);
        abort_unless($response->successful() || $response->status() === 422, 503, 'Member accounts are temporarily unavailable.');
        return response()->json(['message' => $response->json('message'), 'errors' => $response->json('errors') ?: (object) []], $response->status());
    }

    private function authenticateMember(Request $request, bool $register)
    {
        $organisation = $request->attributes->get('organisation');
        abort_unless($organisation?->slug === 'voca', 404);
        $data = $request->validate(array_merge([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
        ], $register ? [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'password_confirmation' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:1024', 'confirmed'],
        ] : []));
        $url = rtrim((string) config('services.voca_members.url'), '/');
        $key = config('services.voca_members.key');
        abort_unless(is_string($key) && strlen($key) >= 32 && (str_starts_with($url, 'https://') || (app()->environment(['local', 'testing']) && str_starts_with($url, 'http://'))), 503, 'Member accounts are not connected yet. Please try again later.');
        $payload = ['email' => strtolower(trim($data['email'])), 'password' => $data['password']];
        if ($register) $payload += ['profile_type' => 'individual', 'fullName' => $data['name'], 'phone' => $data['phone'], 'password_confirmation' => $data['password_confirmation']];
        try {
            $response = Http::acceptJson()->withHeaders(['X-Voca-Member-Key' => $key])->connectTimeout(5)->timeout(15)->withOptions(['allow_redirects' => false])
                ->post($url.'/api/mobile-members/'.($register ? 'register' : 'login'), $payload);
        } catch (ConnectionException $e) {
            abort(503, 'Member accounts are temporarily unavailable. Please try again.');
        }
        if ($response->status() === 422) return response()->json(['message' => $response->json('message') ?: 'Please check your details.', 'errors' => $response->json('errors') ?: (object) []], 422);
        if ($response->status() === 429) return response()->json(['message' => 'Too many attempts. Please try again later.'], 429);
        abort_unless($response->successful(), 503, 'Member accounts are temporarily unavailable. Please try again.');
        $identity = $response->json('user');
        abort_unless(is_array($identity) && is_numeric($identity['id'] ?? null) && ($identity['id'] > 0) && is_string($identity['name'] ?? null) && filter_var($identity['email'] ?? '', FILTER_VALIDATE_EMAIL), 503);
        $user = DB::transaction(function () use ($identity, $organisation) {
            $user = User::where('website_member_id', $identity['id'])->lockForUpdate()->first();
            // An email match alone is never sufficient to link or overwrite a team account.
            $conflict = User::whereRaw('LOWER(email) = ?', [strtolower($identity['email'])])->first();
            abort_if($conflict && (! $user || $conflict->id !== $user->id), 409, 'A separate team account uses this email. Please contact VOCA to link your accounts.');
            if (! $user) {
                $user = new User(['name' => $identity['name'], 'email' => strtolower($identity['email']), 'password' => Str::random(64), 'is_superadmin' => false]);
                $user->website_member_id = $identity['id'];
                $user->save();
            } else {
                $user->update(['name' => $identity['name'], 'email' => strtolower($identity['email'])]);
            }
            $membership = OrganisationMembership::firstOrCreate(['organisation_id' => $organisation->id, 'user_id' => $user->id], ['role' => 'user', 'status' => 'active']);
            abort_unless($membership->status === 'active', 403, 'Your account does not have active access to this organisation.');
            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget('voca_member_token');
        if (is_string($response->json('member_token'))) $request->session()->put('voca_member_token', $response->json('member_token'));
        return response()->json($this->payload($user, $request), $register ? 201 : 200);
    }
}
