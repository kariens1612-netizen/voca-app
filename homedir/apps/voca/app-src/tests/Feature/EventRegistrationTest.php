<?php

namespace Tests\Feature;

use App\Models\Ministry;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_register_and_superadmin_can_scan_ticket_once(): void
    {
        $ministry = Ministry::create([
            'entity' => 'brackenfell',
            'slug' => 'events-test',
            'name' => 'Events Test',
            'description' => 'Test ministry',
        ]);
        $schedule = Schedule::create([
            'ministry_id' => $ministry->id,
            'title' => 'Community Day',
            'date' => now()->addDays(5)->toDateString(),
            'start_time' => '10:00:00',
            'location' => 'Main Hall',
            'is_recurring' => false,
        ]);
        $occurrenceId = $schedule->id.'|'.$schedule->date->toDateString();

        $response = $this->postJson('/events/register', [
            'occurrence_id' => $occurrenceId,
            'name' => 'Test Guest',
            'email' => 'guest@example.com',
            'phone' => '0820000000',
            'party_size' => 2,
        ])->assertCreated();

        $token = $response->json('ticket_token');
        $this->getJson('/events/tickets/'.$token)
            ->assertOk()
            ->assertJsonPath('registration.event_title', 'Community Day');

        $leader = User::factory()->create(['is_superadmin' => true]);
        $this->actingAs($leader)
            ->postJson('/event-portal/check-in', ['code' => 'PH-TICKET:'.$token])
            ->assertOk()
            ->assertJsonPath('registration.name', 'Test Guest');

        $this->actingAs($leader)
            ->postJson('/event-portal/check-in', ['code' => 'PH-TICKET:'.$token])
            ->assertStatus(409)
            ->assertJsonPath('already_checked_in', true);
    }

    public function test_duplicate_email_for_same_occurrence_is_rejected(): void
    {
        $ministry = Ministry::create([
            'entity' => 'brackenfell',
            'slug' => 'duplicate-test',
            'name' => 'Duplicate Test',
            'description' => 'Test ministry',
        ]);
        $schedule = Schedule::create([
            'ministry_id' => $ministry->id,
            'title' => 'Sunday Event',
            'date' => now()->addDays(3)->toDateString(),
            'start_time' => '09:30:00',
            'is_recurring' => false,
        ]);
        $payload = [
            'occurrence_id' => $schedule->id.'|'.$schedule->date->toDateString(),
            'name' => 'Same Guest',
            'email' => 'same@example.com',
            'party_size' => 1,
        ];

        $this->postJson('/events/register', $payload)->assertCreated();
        $this->postJson('/events/register', $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This email address is already registered for the event.');
    }
}
