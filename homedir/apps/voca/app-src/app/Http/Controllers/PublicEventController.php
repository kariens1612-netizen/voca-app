<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PublicEventController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'start' => ['nullable', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
        ]);

        $rangeStart = isset($data['start'])
            ? Carbon::parse($data['start'])->startOfDay()
            : now()->startOfDay();
        $requestedEnd = isset($data['end'])
            ? Carbon::parse($data['end'])->endOfDay()
            : $rangeStart->copy()->addDays(60)->endOfDay();
        $rangeEnd = $requestedEnd->min($rangeStart->copy()->addYear()->endOfDay());

        $schedules = Schedule::query()
            ->where('date', '<=', $rangeEnd->toDateString())
            ->where(function ($query) use ($rangeStart) {
                $query->whereNull('recurrence_until')
                    ->orWhere('recurrence_until', '>=', $rangeStart->toDateString());
            })
            ->with(['assignees:id', 'ministry:id,entity,slug,name'])
            ->get();

        $events = [];
        $expander = app(ScheduleController::class);

        foreach ($schedules as $schedule) {
            if (! $schedule->ministry) {
                continue;
            }

            foreach ($expander->expandSchedule($schedule, $rangeStart, $rangeEnd) as $occurrence) {
                $events[] = [
                    'occurrence_id' => $occurrence['occurrence_id'],
                    'title' => $occurrence['title'],
                    'date' => $occurrence['date'],
                    'start_time' => $occurrence['start_time'],
                    'end_time' => $occurrence['end_time'],
                    'location' => $occurrence['location'],
                    'thumbnail_url' => $occurrence['thumbnail_url'],
                    'ministry' => [
                        'slug' => $schedule->ministry->slug,
                        'name' => $schedule->ministry->name,
                    ],
                ];
            }
        }

        usort($events, fn ($a, $b) => strcmp(
            $a['date'].$a['start_time'],
            $b['date'].$b['start_time']
        ));

        return response()->json(['events' => $events]);
    }
}
