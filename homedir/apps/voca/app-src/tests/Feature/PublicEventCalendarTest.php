<?php

namespace Tests\Feature;

use App\Models\Ministry;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicEventCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_calendar_combines_ministry_events_without_private_fields(): void
    {
        $worship = Ministry::create([
            'entity' => 'brackenfell',
            'slug' => 'worship',
            'name' => 'Worship',
        ]);
        $youth = Ministry::create([
            'entity' => 'brackenfell',
            'slug' => 'youth',
            'name' => 'Youth',
        ]);

        Schedule::create([
            'ministry_id' => $worship->id,
            'title' => 'Worship Night',
            'date' => '2026-08-08',
            'start_time' => '18:00',
            'location' => 'Main Hall',
            'thumbnail_url' => 'https://example.com/worship-night.jpg',
            'notes' => 'Private setup instructions',
        ]);
        Schedule::create([
            'ministry_id' => $youth->id,
            'title' => 'Youth Gathering',
            'date' => '2026-08-09',
            'start_time' => '17:00',
        ]);

        $response = $this->getJson('/events?start=2026-08-01&end=2026-08-31')
            ->assertOk()
            ->assertJsonCount(2, 'events')
            ->assertJsonPath('events.0.ministry.name', 'Worship')
            ->assertJsonPath('events.1.ministry.name', 'Youth');

        $firstEvent = $response->json('events.0');
        $this->assertArrayNotHasKey('notes', $firstEvent);
        $this->assertArrayNotHasKey('assignees', $firstEvent);
        $this->assertSame('Main Hall', $firstEvent['location']);
        $this->assertSame('https://example.com/worship-night.jpg', $firstEvent['thumbnail_url']);
    }

    public function test_superadmin_can_update_schedule_times_returned_with_seconds(): void
    {
        $ministry = Ministry::create([
            'entity' => 'brackenfell',
            'slug' => 'media',
            'name' => 'Media',
        ]);
        $schedule = Schedule::create([
            'ministry_id' => $ministry->id,
            'title' => 'Monthly Gathering',
            'date' => '2026-08-28',
            'start_time' => '19:00',
            'end_time' => '21:00',
            'is_recurring' => true,
            'recurrence_frequency' => 'monthly',
            'recurrence_interval' => 1,
            'recurrence_until' => '2026-11-27',
        ]);
        $superadmin = User::factory()->create(['is_superadmin' => true]);

        $this->actingAs($superadmin)->patchJson(
            "/brackenfell/ministries/media/schedules/{$schedule->id}",
            [
                'title' => 'Updated Monthly Gathering',
                'date' => '2026-08-28',
                'start_time' => '19:30:00',
                'end_time' => '21:30:00',
                'location' => 'Main Hall',
                'notes' => null,
                'assignees' => [],
                'is_recurring' => true,
                'recurrence_frequency' => 'monthly',
                'recurrence_interval' => 1,
                'recurrence_byweekday' => [],
                'recurrence_until' => '2026-11-27',
            ]
        )->assertOk()->assertJsonPath('title', 'Updated Monthly Gathering');

        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'title' => 'Updated Monthly Gathering',
            'start_time' => '19:30',
        ]);
    }
}
