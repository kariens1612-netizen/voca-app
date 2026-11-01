<?php

namespace Tests\Feature;

use App\Models\{MovementRoster, MovementDelegate, MovementJourney, MovementScan, Organisation, OrganisationMembership, User};
use App\Services\DelegateImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class DelegateMovementTest extends TestCase
{
    use RefreshDatabase;

    private function setupJourney(string $kind = 'bus'): array
    {
        $admin = User::factory()->create(['is_superadmin' => true]);
        $this->actingAs($admin);
        $roster = $this->postJson('/api/delegate-movements', ['name' => 'Delegate tour'])->assertCreated()->json();
        $this->postJson('/api/delegate-movements/'.$roster['id'].'/import', ['file' => UploadedFile::fake()->createWithContent('delegates.csv', "Name,Email,Phone,Group\nTest Delegate,delegate@example.org,0123456789,VOCA\n")])->assertOk()->assertJsonPath('imported', 1);
        $delegate = MovementDelegate::firstOrFail();
        $journey = $this->postJson('/api/delegate-movements/'.$roster['id'].'/journeys', [
            'name' => 'Hotel to venue', 'kind' => $kind, 'location' => 'Bus 1', 'expected_ids' => [$delegate->id],
        ])->assertCreated()->json();
        return [$roster['id'], $delegate->id, $journey['id']];
    }

    private function event(int $delegate, int $journey, string $action = 'board', ?string $base = null): array
    {
        return ['event_id' => (string) Str::uuid(), 'delegate_id' => $delegate, 'journey_id' => $journey,
            'action' => $action, 'base_event_id' => $base, 'stop' => 'Hotel', 'recorded_at' => now()->toIso8601String()];
    }

    public function test_offline_batch_is_idempotent_and_rejects_stale_or_invalid_movements(): void
    {
        [$roster, $delegate, $journey] = $this->setupJourney();
        $board = $this->event($delegate, $journey);
        // Device-only metadata and untrusted extra fields must never reach persistence.
        $board['roster_id'] = $roster;
        $board['organisation_id'] = 99999;
        $board['recorded_by'] = 99999;
        $exit = $this->event($delegate, $journey, 'exit', $board['event_id']);
        $url = "/api/delegate-movements/{$roster}/sync";
        $this->postJson($url, ['events' => [$board, $exit]])->assertOk()
            ->assertJsonPath('results.0.status', 'accepted')->assertJsonPath('results.1.status', 'accepted');
        $this->postJson($url, ['events' => [$board, $exit]])->assertOk()->assertJsonPath('results.1.status', 'accepted');
        $this->assertDatabaseCount('movement_scans', 2);
        $this->postJson($url, ['events' => [$this->event($delegate, $journey)]])->assertOk()->assertJsonPath('results.0.status', 'conflict');
        $this->postJson($url, ['events' => [$this->event($delegate, $journey, 'exit', $exit['event_id'])]])->assertOk()->assertJsonPath('results.0.status', 'conflict');
        $this->assertDatabaseCount('movement_scans', 2);
        $this->getJson("/api/delegate-movements/{$roster}")->assertOk()->assertJsonMissing(['email' => 'delegate@example.org']);
    }

    public function test_duplicate_boarding_and_boarding_another_bus_are_rejected(): void
    {
        [$roster, $delegate, $journey] = $this->setupJourney();
        $board = $this->event($delegate, $journey);
        $this->postJson("/api/delegate-movements/{$roster}/sync", ['events' => [$board]])->assertOk()->assertJsonPath('results.0.status', 'accepted');
        $other = MovementJourney::create(['roster_id' => $roster, 'name' => 'Other bus', 'kind' => 'bus', 'location' => 'Bus 2', 'expected_ids' => [$delegate]]);
        foreach ([$journey, $other->id] as $id) {
            $this->postJson("/api/delegate-movements/{$roster}/sync", ['events' => [$this->event($delegate, $id, 'board', $board['event_id'])]])->assertOk()->assertJsonPath('results.0.status', 'conflict');
        }
        $this->assertDatabaseCount('movement_scans', 1);
    }

    public function test_checkpoints_require_arrival_before_departure(): void
    {
        [$roster, $delegate, $journey] = $this->setupJourney('checkpoint');
        $url = "/api/delegate-movements/{$roster}/sync";
        $this->postJson($url, ['events' => [$this->event($delegate, $journey, 'depart')]])->assertOk()->assertJsonPath('results.0.status', 'conflict');
        $arrival = $this->event($delegate, $journey, 'arrive');
        $this->postJson($url, ['events' => [$arrival, $this->event($delegate, $journey, 'depart', $arrival['event_id'])]])->assertOk()->assertJsonPath('results.1.status', 'accepted');
    }

    public function test_permissions_and_tenant_boundaries_are_enforced(): void
    {
        [$roster, $delegate, $journey] = $this->setupJourney();
        $user = User::factory()->create();
        $voca = Organisation::where('slug', 'voca')->firstOrFail();
        OrganisationMembership::create(['user_id' => $user->id, 'organisation_id' => $voca->id, 'role' => 'member', 'status' => 'active']);
        $this->actingAs($user)->getJson('/api/delegate-movements')->assertForbidden();
        OrganisationMembership::where('user_id', $user->id)->update(['role' => 'admin']);
        $this->getJson('/api/delegate-movements')->assertOk();
        $other = Organisation::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active']);
        $foreign = MovementRoster::create(['name' => 'Other roster', 'organisation_id' => $other->id]);
        $this->getJson('/api/delegate-movements/'.$foreign->id)->assertNotFound();
        $foreignDelegate = MovementDelegate::create(['organisation_id' => $other->id, 'roster_id' => $foreign->id, 'name' => 'Private delegate', 'reference' => 'PRIVATE']);
        $this->postJson("/api/delegate-movements/{$roster}/journeys", ['name' => 'Bad', 'kind' => 'bus', 'location' => 'Bus', 'expected_ids' => [$foreignDelegate->id]])->assertUnprocessable();
        $this->postJson("/api/delegate-movements/{$roster}/sync", ['events' => [$this->event($foreignDelegate->id, $journey)]])->assertOk()->assertJsonPath('results.0.status', 'conflict');
    }

    public function test_import_is_atomic_and_reimport_preserves_badges(): void
    {
        [$roster] = $this->setupJourney();
        $reference = MovementDelegate::first()->reference;
        $file = UploadedFile::fake()->createWithContent('again.csv', "Name,Email\nChanged Name,delegate@example.org\n");
        $this->postJson("/api/delegate-movements/{$roster}/import", ['file' => $file])->assertOk()->assertJsonPath('imported', 0);
        $this->assertSame($reference, MovementDelegate::first()->reference);
        $invalid = UploadedFile::fake()->createWithContent('bad.csv', "Name,Email\nValid,valid@example.org\nInvalid,broken-email\n");
        $this->postJson("/api/delegate-movements/{$roster}/import", ['file' => $invalid])->assertUnprocessable();
        $this->assertDatabaseCount('movement_delegates', 1);
    }

    public function test_xlsx_contact_import_reads_shared_and_inline_strings_with_blank_first_column(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'delegate-xlsx');
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::OVERWRITE);
        $namespace = 'xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
        $zip->addFromString('xl/sharedStrings.xml', '<sst '.$namespace.'><si><t>Name</t></si><si><r><t>Test </t></r><r><t>Delegate</t></r></si><si><t>E-mail 1 - Value</t></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet '.$namespace.'><sheetData><row r="1"><c r="B1" t="s"><v>0</v></c><c r="F1" t="s"><v>2</v></c></row><row r="2"><c r="B2" t="s"><v>1</v></c><c r="F2" t="inlineStr"><is><t>test@example.org</t></is></c></row></sheetData></worksheet>');
        $zip->close();
        try {
            $rows = app(DelegateImport::class)->read($path, true);
            $this->assertCount(1, $rows);
            $this->assertSame('Test Delegate', $rows[0]['name']);
            $this->assertSame('test@example.org', $rows[0]['email']);
        } finally { unlink($path); }
    }
}
