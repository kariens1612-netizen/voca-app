<?php
namespace Tests\Feature;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class SharedMemberAccountTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void {
        parent::setUp();
        Organisation::firstOrCreate(['slug'=>'voca'],['name'=>'VOCA','status'=>'active']);
        config(['services.voca_members.url'=>'https://voca.africa','services.voca_members.key'=>str_repeat('x',40)]);
        Http::preventStrayRequests();
    }
    private function url(string $path): string { return 'http://voca.app.voca.co.za/auth/'.$path; }
    private function credentials(): array { return ['email'=>'MEMBER@example.com','password'=>'SharedPassword123']; }
    private function identity(): array { return ['user'=>['id'=>72,'name'=>'Member','email'=>'member@example.com']]; }
    public function test_login_creates_only_a_regular_member_without_copying_password(): void {
        Http::fake(['*'=>Http::response($this->identity())]);
        $this->postJson($this->url('member-login'),$this->credentials())->assertOk()->assertJsonPath('organisation_role','user')->assertJsonPath('user.is_superadmin',false);
        $user=User::firstOrFail();
        $this->assertEquals(72,$user->website_member_id);
        $this->assertFalse(Hash::check('SharedPassword123',$user->password));
        $this->assertAuthenticatedAs($user);
        Http::assertSent(fn($r)=>$r['email']==='member@example.com' && $r->hasHeader('X-Voca-Member-Key',str_repeat('x',40)));
    }
    public function test_registration_creates_website_member_and_reuses_local_identity(): void {
        Http::fake(['*'=>Http::response($this->identity(),201)]);
        $this->postJson($this->url('member-register'),$this->credentials()+['name'=>'Member','phone'=>'0123456789','password_confirmation'=>'SharedPassword123'])->assertCreated();
        Http::assertSent(fn($r)=>$r['profile_type']==='individual' && $r['fullName']==='Member');
        $this->postJson($this->url('member-login'),$this->credentials())->assertOk();
        $this->assertDatabaseCount('users',1);
        $this->assertDatabaseCount('organisation_memberships',1);
    }
    public function test_bad_credentials_and_outages_do_not_create_users(): void {
        Http::fake(['*'=>Http::sequence()->push(['message'=>'Invalid credentials.'],422)->push([],500)]);
        $this->postJson($this->url('member-login'),$this->credentials())->assertUnprocessable();
        $this->postJson($this->url('member-login'),$this->credentials())->assertStatus(503);
        $this->assertDatabaseCount('users',0); $this->assertGuest();
    }
    public function test_email_collision_cannot_claim_existing_team_account(): void {
        $user=User::factory()->create(['email'=>'member@example.com','is_superadmin'=>true]);
        Http::fake(['*'=>Http::response($this->identity())]);
        $this->postJson($this->url('member-login'),$this->credentials())->assertStatus(409);
        $this->assertNull($user->fresh()->website_member_id); $this->assertGuest();
    }
    public function test_suspension_is_preserved(): void {
        $user=User::factory()->create(['email'=>'member@example.com']); $user->website_member_id=72; $user->save();
        OrganisationMembership::create(['user_id'=>$user->id,'organisation_id'=>Organisation::where('slug','voca')->first()->id,'role'=>'user','status'=>'suspended']);
        Http::fake(['*'=>Http::response($this->identity())]);
        $this->postJson($this->url('member-login'),$this->credentials())->assertForbidden(); $this->assertGuest();
    }
    public function test_other_tenants_cannot_use_member_login(): void {
        Organisation::create(['name'=>'Other','slug'=>'other','status'=>'active']);
        $this->postJson('http://other.app.voca.co.za/auth/member-login',$this->credentials())->assertNotFound(); Http::assertNothingSent();
    }
    public function test_recovery_uses_website_and_local_login_cannot_bypass_it(): void {
        Http::fake(['*'=>Http::response(['message'=>'Password link sent.'])]);
        $this->postJson($this->url('member-forgot-password'),['email'=>'member@example.com'])->assertOk();
        Http::assertSent(fn($r)=>str_ends_with($r->url(),'/api/mobile-members/forgot-password'));
        $user=User::factory()->create(['email'=>'member@example.com','password'=>Hash::make('SharedPassword123')]); $user->website_member_id=72; $user->save();
        $this->postJson($this->url('login'),$this->credentials())->assertUnprocessable();
    }
    public function test_member_reset_forwards_token_and_confirmation_to_website(): void {
        Http::fake(['*'=>Http::response(['message'=>'Password saved. Use your new password on the website and app.'])]);
        $this->postJson($this->url('member-reset-password'), [
            'email'=>'MEMBER@example.com', 'token'=>'reset-token',
            'password'=>'NewPassword12345', 'password_confirmation'=>'NewPassword12345',
        ])->assertOk()->assertJsonPath('message','Password saved. Use your new password on the website and app.');
        Http::assertSent(fn($r)=>str_ends_with($r->url(),'/api/mobile-members/reset-password')
            && $r['email']==='member@example.com' && $r['token']==='reset-token'
            && $r['password_confirmation']==='NewPassword12345'
            && $r->hasHeader('X-Voca-Member-Key',str_repeat('x',40)));
        $this->assertDatabaseCount('users',0);
    }
    public function test_member_reset_preserves_expired_token_errors(): void {
        Http::fake(['*'=>Http::response(['message'=>'This password link is invalid or expired.'],422)]);
        $this->postJson($this->url('member-reset-password'), [
            'email'=>'member@example.com', 'token'=>'expired-token',
            'password'=>'NewPassword12345', 'password_confirmation'=>'NewPassword12345',
        ])->assertUnprocessable()->assertJsonPath('message','This password link is invalid or expired.');
    }

    public function test_dashboard_uses_server_session_credential_without_exposing_it(): void {
        Http::fake([
            'https://voca.africa/api/mobile-members/login' => Http::response($this->identity() + ['member_token' => 'opaque-server-token']),
            'https://voca.africa/api/mobile-members/dashboard-link' => Http::response(['url' => 'https://voca.africa/member-dashboard/open?code=one-time']),
        ]);
        $response = $this->postJson($this->url('member-login'), $this->credentials())->assertOk()->assertJsonPath('user.is_voca_member', true);
        $this->assertArrayNotHasKey('member_token', $response->json());
        $this->postJson($this->url('member-dashboard'))->assertOk()->assertJsonPath('url', 'https://voca.africa/member-dashboard/open?code=one-time');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/dashboard-link') && $request->hasHeader('Authorization', 'Bearer opaque-server-token'));
    }

    public function test_dashboard_rejects_external_redirect_and_missing_session(): void {
        Http::fake(['*' => Http::response($this->identity() + ['member_token' => 'opaque-server-token'])]);
        $this->postJson($this->url('member-login'), $this->credentials())->assertOk();
        Http::fake(['*' => Http::response(['url' => 'https://attacker.example/member-dashboard/open?code=stolen'])]);
        $this->postJson($this->url('member-dashboard'))->assertStatus(503);
        $this->postJson($this->url('logout'))->assertNoContent();
        $this->postJson($this->url('member-dashboard'))->assertUnauthorized();
    }
}
