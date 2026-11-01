<?php

namespace Tests\Feature;

use App\Models\Ministry;
use App\Models\MinistryMembership;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\User;
use App\Notifications\AccountInvitationNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AccountAccessTest extends TestCase
{
    use RefreshDatabase;

    private function organisation(string $slug = 'alpha'): Organisation
    {
        return Organisation::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => 'active']);
    }

    private function member(Organisation $organisation, string $role = 'user', string $status = 'active'): User
    {
        $user = User::factory()->create(['password' => Hash::make('OriginalPassword123')]);
        OrganisationMembership::create(['organisation_id' => $organisation->id, 'user_id' => $user->id, 'role' => $role, 'status' => $status]);

        return $user;
    }

    private function url(string $path): string
    {
        return 'http://alpha.app.voca.co.za'.$path;
    }

    public function test_invitation_email_provides_a_single_use_password_setup_link(): void
    {
        Notification::fake();
        $organisation = $this->organisation();
        $admin = $this->member($organisation, 'admin');
        $this->actingAs($admin)->postJson($this->url('/api/admin/users'), [
            'name' => 'Invited Member', 'email' => 'NEW@example.com', 'role' => 'user',
        ])->assertCreated()->assertJsonPath('invitation_sent', true);
        $user = User::where('email', 'new@example.com')->firstOrFail();
        $notification = Notification::sent($user, AccountInvitationNotification::class)->first();
        $this->assertStringStartsWith(rtrim(config('app.url'), '/').'/alpha/reset-password/', $notification->url);
        $token = basename(parse_url($notification->url, PHP_URL_PATH));
        auth()->logout();
        $data = ['email' => $user->email, 'token' => $token, 'password' => 'NewPassword12345', 'password_confirmation' => 'NewPassword12345'];
        $this->postJson($this->url('/auth/reset-password'), $data)->assertOk();
        $this->assertTrue(Hash::check($data['password'], $user->fresh()->password));
        $this->postJson($this->url('/auth/reset-password'), $data)->assertUnprocessable();
        $this->postJson($this->url('/auth/login'), ['email' => strtoupper($user->email), 'password' => $data['password']])->assertOk();
    }

    public function test_recovery_does_not_disclose_accounts_or_send_links_for_other_organisations(): void
    {
        Notification::fake();
        $organisation = $this->organisation();
        $other = $this->organisation('beta');
        $user = $this->member($organisation);
        $outsider = $this->member($other);
        $response = $this->postJson($this->url('/auth/forgot-password'), ['email' => $user->email])->assertOk();
        $this->postJson($this->url('/auth/forgot-password'), ['email' => $outsider->email])->assertExactJson($response->json());
        $this->postJson($this->url('/auth/forgot-password'), ['email' => 'missing@example.com'])->assertExactJson($response->json());
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->assertStringStartsWith(rtrim(config('app.url'), '/').'/alpha/reset-password/', $notification->toMail($user)->actionUrl);

            return true;
        });
        Notification::assertNotSentTo($outsider, ResetPassword::class);
    }

    public function test_password_reset_requires_strong_confirmed_password_and_unexpired_token(): void
    {
        $user = $this->member($this->organisation());
        $token = Password::createToken($user);
        $data = ['email' => $user->email, 'token' => $token, 'password' => 'short', 'password_confirmation' => 'different'];
        $this->postJson($this->url('/auth/reset-password'), $data)->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->travel(61)->minutes();
        $data['password'] = $data['password_confirmation'] = 'NewPassword12345';
        $this->postJson($this->url('/auth/reset-password'), $data)->assertUnprocessable();
        $this->assertTrue(Hash::check('OriginalPassword123', $user->fresh()->password));
    }

    public function test_reset_link_cannot_be_used_on_an_unrelated_tenant(): void
    {
        $this->organisation();
        $user = $this->member($this->organisation('beta'));
        $this->postJson($this->url('/auth/reset-password'), [
            'email' => $user->email, 'token' => Password::createToken($user),
            'password' => 'NewPassword12345', 'password_confirmation' => 'NewPassword12345',
        ])->assertUnprocessable();
    }

    public function test_login_is_rate_limited(): void
    {
        $this->organisation();
        $email = 'ratelimited@example.com';
        RateLimiter::clear($email.'|127.0.0.1');
        for ($i = 0; $i < 5; $i++) {
            $this->postJson($this->url('/auth/login'), ['email' => $email, 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson($this->url('/auth/login'), ['email' => $email, 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_suspended_member_cannot_login_or_keep_using_an_existing_session(): void
    {
        $organisation = $this->organisation();
        $user = $this->member($organisation, 'admin', 'suspended');
        $ministry = Ministry::create(['organisation_id' => $organisation->id, 'entity' => 'alpha', 'slug' => 'team', 'name' => 'Team']);
        MinistryMembership::create(['organisation_id' => $organisation->id, 'ministry_id' => $ministry->id, 'user_id' => $user->id, 'role' => 'admin']);
        $this->postJson($this->url('/auth/login'), ['email' => $user->email, 'password' => 'OriginalPassword123'])->assertForbidden();
        $this->assertGuest();
        $this->actingAs($user)->getJson($this->url('/brackenfell/ministries/team/members'))->assertForbidden();
        $this->actingAs($user)->getJson($this->url('/api/bootstrap'))->assertForbidden();
        $this->postJson($this->url('/auth/logout'))->assertNoContent();
    }

    public function test_inviting_existing_owner_cannot_demote_them_or_change_their_password(): void
    {
        Notification::fake();
        $organisation = $this->organisation();
        $admin = $this->member($organisation, 'admin');
        $owner = $this->member($organisation, 'owner');
        $this->actingAs($admin)->postJson($this->url('/api/admin/users'), [
            'name' => 'Changed', 'email' => $owner->email, 'role' => 'user',
        ])->assertCreated()->assertJsonPath('data.role', 'owner');
        $this->assertTrue(Hash::check('OriginalPassword123', $owner->fresh()->password));
        $this->actingAs($admin)->patchJson($this->url('/api/admin/users/'.$owner->organisationMemberships()->first()->id), ['role' => 'admin'])->assertForbidden();
    }

    public function test_organisation_admin_cannot_manage_other_tenants_membership_or_attach_a_global_user_id(): void
    {
        $alpha = $this->organisation();
        $beta = $this->organisation('beta');
        $admin = $this->member($alpha, 'admin');
        $outsider = $this->member($beta);
        $membership = $outsider->organisationMemberships()->first();
        $this->actingAs($admin)->patchJson($this->url('/api/admin/users/'.$membership->id), ['role' => 'admin'])->assertNotFound();
        $this->postJson($this->url('/api/admin/users/'.$membership->id.'/invite'))->assertNotFound();
        $this->postJson($this->url('/api/admin/users'), ['user_id' => $outsider->id, 'role' => 'user'])->assertNotFound();
    }

    public function test_platform_admin_cannot_demote_or_suspend_last_owner(): void
    {
        $organisation = $this->organisation();
        $owner = $this->member($organisation, 'owner');
        $super = User::factory()->create(['is_superadmin' => true]);
        $path = '/api/platform/organisations/'.$organisation->id.'/users/'.$owner->organisationMemberships()->first()->id;
        $this->actingAs($super)->patchJson($path, ['role' => 'user'])->assertUnprocessable();
        $this->patchJson($path, ['status' => 'suspended'])->assertUnprocessable();
    }

    public function test_removing_organisation_access_also_removes_ministry_access(): void
    {
        $organisation = $this->organisation();
        $owner = $this->member($organisation, 'owner');
        $user = $this->member($organisation);
        $ministry = Ministry::create(['organisation_id' => $organisation->id, 'entity' => 'alpha', 'slug' => 'team', 'name' => 'Team']);
        MinistryMembership::create(['organisation_id' => $organisation->id, 'ministry_id' => $ministry->id, 'user_id' => $user->id, 'role' => 'member']);
        $this->actingAs($owner)->deleteJson($this->url('/api/admin/users/'.$user->organisationMemberships()->first()->id))->assertNoContent();
        $this->assertFalse($user->fresh()->canAccessOrganisation($organisation->id));
        $this->assertDatabaseMissing('ministry_memberships', ['user_id' => $user->id, 'organisation_id' => $organisation->id]);
    }

    public function test_invitation_can_be_resent_and_is_throttled(): void
    {
        Notification::fake();
        $organisation = $this->organisation();
        $owner = $this->member($organisation, 'owner');
        $user = $this->member($organisation);
        $url = $this->url('/api/admin/users/'.$user->organisationMemberships()->first()->id.'/invite');
        $this->actingAs($owner)->postJson($url)->assertOk()->assertJsonPath('message', 'Invitation sent.');
        $this->postJson($url)->assertOk()->assertJsonPath('message', 'A link was sent recently. Please wait before resending.');
        Notification::assertSentToTimes($user, AccountInvitationNotification::class, 1);
    }

    public function test_reset_revokes_database_sessions_without_affecting_other_users(): void
    {
        $user = $this->member($this->organisation());
        $other = $this->member(Organisation::where('slug', 'alpha')->first());
        config(['session.driver' => 'database']);
        foreach ([$user, $other] as $account) {
            DB::table('sessions')->insert([
                'id' => 'device-'.$account->id, 'user_id' => $account->id,
                'payload' => base64_encode(serialize([])), 'last_activity' => time(),
            ]);
        }
        $this->postJson($this->url('/auth/reset-password'), [
            'email' => $user->email, 'token' => Password::createToken($user),
            'password' => 'NewPassword12345', 'password_confirmation' => 'NewPassword12345',
        ])->assertOk();
        $this->assertDatabaseMissing('sessions', ['id' => 'device-'.$user->id]);
        $this->assertDatabaseHas('sessions', ['id' => 'device-'.$other->id]);
    }

    public function test_session_with_an_old_password_hash_is_rejected(): void
    {
        $user = $this->member($this->organisation());
        $oldHash = $user->password;
        $user->forceFill(['password' => Hash::make('ChangedPassword123')])->save();
        $this->actingAs($user)->withSession(['password_hash_web' => $oldHash])
            ->getJson($this->url('/auth/me'))->assertUnauthorized();
    }

    public function test_ministry_member_list_cannot_enumerate_other_organisations_users(): void
    {
        $alpha = $this->organisation();
        $admin = $this->member($alpha, 'admin');
        $outsider = $this->member($this->organisation('beta'));
        Ministry::create(['organisation_id' => $alpha->id, 'entity' => 'alpha', 'slug' => 'team', 'name' => 'Team']);
        $this->actingAs($admin)->getJson($this->url('/brackenfell/ministries/team/members/available'))
            ->assertOk()->assertJsonMissing(['email' => $outsider->email]);
        $this->postJson($this->url('/brackenfell/ministries/team/members'), ['user_id' => $outsider->id])->assertNotFound();
    }

    public function test_ordinary_user_cannot_manage_accounts_or_platform(): void
    {
        $user = $this->member($this->organisation());
        $this->actingAs($user)->getJson($this->url('/api/admin/users'))->assertForbidden();
        $this->getJson($this->url('/api/platform/organisations'))->assertForbidden();
        $this->postJson($this->url('/api/admin/users'), ['name' => 'Escalation', 'email' => 'escalate@example.com', 'role' => 'owner'])->assertForbidden();
    }
    public function test_revoked_session_does_not_block_signing_into_another_active_account(): void
    {
        $organisation = $this->organisation();
        $suspended = $this->member($organisation, 'user', 'suspended');
        $active = $this->member($organisation);
        $this->actingAs($suspended)->get($this->url('/login'))->assertOk();
        $this->postJson($this->url('/auth/login'), ['email' => $active->email, 'password' => 'OriginalPassword123'])
            ->assertOk()->assertJsonPath('user.id', $active->id);
    }

    public function test_suspended_users_cannot_receive_or_use_password_links(): void
    {
        Notification::fake();
        $organisation = $this->organisation();
        $user = $this->member($organisation, 'user', 'suspended');
        $this->postJson($this->url('/auth/forgot-password'), ['email' => $user->email])->assertOk();
        Notification::assertNothingSent();
        $this->postJson($this->url('/auth/reset-password'), [
            'email' => $user->email, 'token' => Password::createToken($user),
            'password' => 'NewPassword12345', 'password_confirmation' => 'NewPassword12345',
        ])->assertUnprocessable();
    }

}
