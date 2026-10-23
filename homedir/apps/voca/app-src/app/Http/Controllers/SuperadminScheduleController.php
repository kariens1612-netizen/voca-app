<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SuperadminScheduleController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->is_superadmin) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $start = $request->input('start');
        $end = $request->input('end');
        $rangeStart = $start ? Carbon::parse($start)->startOfDay() : now()->startOfDay();
        $rangeEnd = $end ? Carbon::parse($end)->endOfDay() : now()->addDays(30)->endOfDay();

        $schedules = Schedule::query()
            ->where('date', '<=', $rangeEnd->toDateString())
            ->where(function ($query) use ($rangeStart) {
                $query->whereNull('recurrence_until')
                    ->orWhere('recurrence_until', '>=', $rangeStart->toDateString());
            })
            ->with(['assignees:id,name,email', 'ministry:id,entity,slug,name'])
            ->get();

        $occurrences = [];
        $expander = app(ScheduleController::class);
        foreach ($schedules as $schedule) {
            $items = $expander->expandSchedule($schedule, $rangeStart, $rangeEnd);
            foreach ($items as $item) {
                $item['ministry'] = [
                    'id' => $schedule->ministry->id,
                    'entity' => $schedule->ministry->entity,
                    'slug' => $schedule->ministry->slug,
                    'name' => $schedule->ministry->name,
                ];
                $occurrences[] = $item;
            }
        }

        usort($occurrences, function ($a, $b) {
            return strcmp($a['date'] . $a['start_time'], $b['date'] . $b['start_time']);
        });

        return response()->json(['occurrences' => $occurrences]);
    }
}
