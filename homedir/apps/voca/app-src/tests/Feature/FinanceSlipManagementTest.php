<?php

namespace Tests\Feature;

use App\Models\FinanceSlip;
use App\Models\Ministry;
use App\Models\MinistryMembership;
use App\Models\Organisation;
use App\Models\OrganisationMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinanceSlipManagementTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug = 'alpha'): Organisation
    {
        return Organisation::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'status' => 'active',
            'modules' => Organisation::DEFAULT_MODULES,
        ]);
    }

    private function url(string $slug, string $path): string
    {
        return 'http://'.$slug.'.app.voca.co.za'.$path;
    }

    public function test_ministry_member_can_upload_slip_but_cannot_view_finance_dashboard_data(): void
    {
        Storage::fake('public');
        $organisation = $this->tenant();
        $ministry = Ministry::create([
            'organisation_id' => $organisation->id,
            'entity' => 'brackenfell',
            'slug' => 'worship',
            'name' => 'Worship',
        ]);
        $member = User::factory()->create();
        MinistryMembership::create(['organisation_id' => $organisation->id, 'user_id' => $member->id, 'ministry_id' => $ministry->id, 'role' => 'member']);

        $this->actingAs($member)->post($this->url('alpha', '/api/finance/slips'), [
            'ministry_id' => $ministry->id,
            'slip' => UploadedFile::fake()->image('receipt.jpg'),
            'supplier' => 'Music Shop',
            'amount' => '199.99',
        ])->assertCreated()->assertJsonPath('data.status', 'submitted');

        $slip = FinanceSlip::withoutGlobalScopes()->firstOrFail();
        Storage::disk('public')->assertExists($slip->path);

        $this->actingAs($member)
            ->getJson($this->url('alpha', '/api/finance/slips'))
            ->assertForbidden();
    }

    public function test_finance_ministry_member_can_review_slips(): void
    {
        Storage::fake('public');
        $organisation = $this->tenant();
        $worship = Ministry::create(['organisation_id' => $organisation->id, 'entity' => 'brackenfell', 'slug' => 'worship', 'name' => 'Worship']);
        $finance = Ministry::create(['organisation_id' => $organisation->id, 'entity' => 'brackenfell', 'slug' => 'finance', 'name' => 'Finance']);
        $uploader = User::factory()->create();
        $financeUser = User::factory()->create();
        MinistryMembership::create(['organisation_id' => $organisation->id, 'user_id' => $uploader->id, 'ministry_id' => $worship->id, 'role' => 'member']);
        MinistryMembership::create(['organisation_id' => $organisation->id, 'user_id' => $financeUser->id, 'ministry_id' => $finance->id, 'role' => 'member']);

        $this->actingAs($uploader)->post($this->url('alpha', '/api/finance/slips'), [
            'ministry_id' => $worship->id,
            'slip' => UploadedFile::fake()->create('receipt.pdf', 20, 'application/pdf'),
        ])->assertCreated();

        $slip = FinanceSlip::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($financeUser)
            ->getJson($this->url('alpha', '/api/finance/slips'))
            ->assertOk()
            ->assertJsonPath('data.data.0.name', 'receipt.pdf');

        $this->actingAs($financeUser)
            ->patchJson($this->url('alpha', '/api/finance/slips/'.$slip->id), ['status' => 'captured'])
            ->assertOk()
            ->assertJsonPath('data.status', 'captured');
    }

    public function test_organisation_admin_has_finance_access(): void
    {
        $organisation = $this->tenant();
        $admin = User::factory()->create();
        OrganisationMembership::create([
            'organisation_id' => $organisation->id,
            'user_id' => $admin->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->getJson($this->url('alpha', '/api/finance/config'))
            ->assertOk()
            ->assertJsonStructure(['akaunting_url']);
    }
}
