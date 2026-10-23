<?php

namespace App\Http\Controllers;

use App\Models\Ministry;
use App\Models\MediaAsset;
use App\Models\Schedule;
use App\Models\ScheduleAudit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScheduleController extends Controller
{
    public function index(Request $request, Ministry $ministry)
    {
        [$rangeStart, $rangeEnd] = $this->resolveRange($request);

        $schedules = Schedule::query()
            ->where('ministry_id', $ministry->id)
            ->where('date', '<=', $rangeEnd->toDateString())
            ->where(function ($query) use ($rangeStart) {
                $query->whereNull('recurrence_until')
                    ->orWhere('recurrence_until', '>=', $rangeStart->toDateString());
            })
            ->with(['assignees:id,name,email'])
            ->get();

        $occurrences = [];
        foreach ($schedules as $schedule) {
            $occurrences = array_merge(
                $occurrences,
                $this->expandSchedule($schedule, $rangeStart, $rangeEnd)
            );
        }

        usort($occurrences, function ($a, $b) {
            return strcmp($a['date'] . $a['start_time'], $b['date'] . $b['start_time']);
        });

        return response()->json(['occurrences' => $occurrences]);
    }

    public function audits(Request $request, Ministry $ministry)
    {
        $limit = (int) $request->input('limit', 50);
        $limit = max(10, min(200, $limit));

        $start = $request->input('start');
        $end = $request->input('end');
        $rangeStart = $start ? Carbon::parse($start)->startOfDay() : null;
        $rangeEnd = $end ? Carbon::parse($end)->endOfDay() : null;

        $query = ScheduleAudit::query()
            ->where('ministry_id', $ministry->id)
            ->with(['actor:id,name,email', 'schedule:id,title,date,start_time'])
            ->orderByDesc('occurred_at');

        if ($rangeStart) {
            $query->where('occurred_at', '>=', $rangeStart);
        }
        if ($rangeEnd) {
            $query->where('occurred_at', '<=', $rangeEnd);
        }

        $audits = $query
            ->limit($limit)
            ->get()
            ->map(fn ($audit) => [
                'id' => $audit->id,
                'action' => $audit->action,
                'occurred_at' => $audit->occurred_at?->toDateTimeString(),
                'actor' => $audit->actor ? [
                    'id' => $audit->actor->id,
                    'name' => $audit->actor->name,
                    'email' => $audit->actor->email,
                ] : null,
                'schedule' => $audit->schedule ? [
                    'id' => $audit->schedule->id,
                    'title' => $audit->schedule->title,
                    'date' => $audit->schedule->date?->toDateString(),
                    'start_time' => $audit->schedule->start_time,
                ] : null,
                'payload' => $audit->payload,
            ])
            ->values();

        return response()->json(['audits' => $audits]);
    }

    public function store(Request $request, Ministry $ministry)
    {
        $data = $this->validateSchedule($request);
        $assignees = $data['assignees'] ?? [];
        if (! $this->canAssign($request, $ministry)) {
            $assignees = [];
        }

        $schedule = Schedule::create([
            'ministry_id' => $ministry->id,
            'title' => $data['title'],
            'date' => $data['date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'] ?? null,
            'location' => $data['location'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_recurring' => $data['is_recurring'],
            'recurrence_frequency' => $data['recurrence_frequency'] ?? null,
            'recurrence_interval' => $data['recurrence_interval'] ?? 1,
            'recurrence_byweekday' => $data['recurrence_byweekday'] ?? null,
            'recurrence_until' => $data['recurrence_until'] ?? null,
        ]);

        $this->syncAssignees($schedule, $ministry, $assignees);
        $schedule->load(['assignees:id,name,email']);
        $this->recordAudit($request, $ministry, $schedule, 'created', [
            'after' => $this->auditSnapshot($schedule),
        ]);

        return response()->json($this->payload($schedule));
    }

    public function update(Request $request, Ministry $ministry, Schedule $schedule)
    {
        if ($schedule->ministry_id !== $ministry->id) {
            return response()->json(['message' => 'Schedule not found.'], 404);
        }

        $data = $this->validateSchedule($request);
        $assignees = $data['assignees'] ?? [];
        if (! $this->canAssign($request, $ministry)) {
            $assignees = [];
        }
        $before = $this->auditSnapshot($schedule);

        $schedule->update([
            'title' => $data['title'],
            'date' => $data['date'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'] ?? null,
            'location' => $data['location'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_recurring' => $data['is_recurring'],
            'recurrence_frequency' => $data['recurrence_frequency'] ?? null,
            'recurrence_interval' => $data['recurrence_interval'] ?? 1,
            'recurrence_byweekday' => $data['recurrence_byweekday'] ?? null,
            'recurrence_until' => $data['recurrence_until'] ?? null,
        ]);

        $this->syncAssignees($schedule, $ministry, $assignees);
        $schedule->load(['assignees:id,name,email']);
        $this->recordAudit($request, $ministry, $schedule, 'updated', [
            'before' => $before,
            'after' => $this->auditSnapshot($schedule),
        ]);

        return response()->json($this->payload($schedule));
    }

    public function destroy(Request $request, Ministry $ministry, Schedule $schedule)
    {
        if ($schedule->ministry_id !== $ministry->id) {
            return response()->json(['message' => 'Schedule not found.'], 404);
        }

        $before = $this->auditSnapshot($schedule);
        $schedule->delete();
        $this->recordAudit($request, $ministry, $schedule, 'deleted', [
            'before' => $before,
        ]);

        return response()->noContent();
    }

    protected function validateSchedule(Request $request): array
    {
        foreach (['start_time', 'end_time'] as $field) {
            $value = $request->input($field);
            if (is_string($value) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
                $request->merge([$field => substr($value, 0, 5)]);
            }
        }

        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:200'],
            'thumbnail_url' => ['nullable', 'url', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'assignees' => ['nullable', 'array'],
            'assignees.*' => ['integer', 'exists:users,id'],
            'is_recurring' => ['required', 'boolean'],
            'recurrence_frequency' => [
                'nullable',
                'string',
                Rule::in(['daily', 'weekly', 'monthly']),
            ],
            'recurrence_interval' => ['nullable', 'integer', 'min:1', 'max:365'],
            'recurrence_byweekday' => ['nullable', 'array'],
            'recurrence_byweekday.*' => ['integer', 'min:1', 'max:7'],
            'recurrence_until' => ['nullable', 'date'],
        ]);
    }

    protected function syncAssignees(Schedule $schedule, Ministry $ministry, array $assignees): void
    {
        if (empty($assignees)) {
            $schedule->assignees()->sync([]);
            return;
        }

        $allowedIds = $ministry->memberships()
            ->whereIn('user_id', $assignees)
            ->pluck('user_id')
            ->toArray();

        $organisationId = app(\App\Tenancy\CurrentOrganisation::class)->id();
        $schedule->assignees()->sync(collect($allowedIds)
            ->mapWithKeys(fn ($id) => [$id => ['organisation_id' => $organisationId]])->all());
    }

    protected function auditSnapshot(Schedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'ministry_id' => $schedule->ministry_id,
            'title' => $schedule->title,
            'date' => $schedule->date?->toDateString(),
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'location' => $schedule->location,
            'thumbnail_url' => $schedule->thumbnail_url,
            'notes' => $schedule->notes,
            'is_recurring' => $schedule->is_recurring,
            'recurrence_frequency' => $schedule->recurrence_frequency,
            'recurrence_interval' => $schedule->recurrence_interval,
            'recurrence_byweekday' => $schedule->recurrence_byweekday,
            'recurrence_until' => $schedule->recurrence_until?->toDateString(),
            'assignees' => $schedule->assignees->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->values(),
        ];
    }

    protected function recordAudit(Request $request, Ministry $ministry, ?Schedule $schedule, string $action, array $payload): void
    {
        ScheduleAudit::create([
            'ministry_id' => $ministry->id,
            'schedule_id' => $schedule?->id,
            'actor_id' => $request->user()?->id,
            'action' => $action,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }

    protected function canAssign(Request $request, Ministry $ministry): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }
        if ($user->is_superadmin) {
            return true;
        }

        $membership = $user->memberships()
            ->where('ministry_id', $ministry->id)
            ->with('roleRecord')
            ->first();

        if (! $membership) {
            return false;
        }

        return in_array($membership->roleSlug(), ['admin', 'leader'], true);
    }

    protected function payload(Schedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'title' => $schedule->title,
            'date' => $schedule->date?->toDateString(),
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'location' => $schedule->location,
            'thumbnail_url' => $this->publicStorageUrl($schedule->thumbnail_url),
            'notes' => $schedule->notes,
            'is_recurring' => $schedule->is_recurring,
            'recurrence_frequency' => $schedule->recurrence_frequency,
            'recurrence_interval' => $schedule->recurrence_interval,
            'recurrence_byweekday' => $schedule->recurrence_byweekday,
            'recurrence_until' => $schedule->recurrence_until?->toDateString(),
            'assignees' => $schedule->assignees->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->values(),
        ];
    }

    protected function resolveRange(Request $request): array
    {
        $start = $request->input('start');
        $end = $request->input('end');

        $rangeStart = $start ? Carbon::parse($start)->startOfDay() : now()->startOfDay();
        $rangeEnd = $end ? Carbon::parse($end)->endOfDay() : now()->addDays(30)->endOfDay();

        return [$rangeStart, $rangeEnd];
    }

    public function expandSchedule(Schedule $schedule, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $occurrences = [];
        $seriesStart = $schedule->date->copy();
        $seriesEnd = $schedule->recurrence_until
            ? $schedule->recurrence_until->copy()->endOfDay()
            : $rangeEnd->copy();

        if ($seriesEnd->lt($rangeStart)) {
            return [];
        }

        if (! $schedule->is_recurring) {
            if ($seriesStart->betweenIncluded($rangeStart, $rangeEnd)) {
                $occurrences[] = $this->occurrencePayload($schedule, $seriesStart);
            }
            return $occurrences;
        }

        $frequency = $schedule->recurrence_frequency ?? 'weekly';
        $interval = max(1, (int) ($schedule->recurrence_interval ?: 1));

        if ($frequency === 'daily') {
            $current = $seriesStart->copy();
            if ($current->lt($rangeStart)) {
                $daysDiff = $current->diffInDays($rangeStart);
                $skip = intdiv($daysDiff, $interval);
                $current->addDays($skip * $interval);
                if ($current->lt($rangeStart)) {
                    $current->addDays($interval);
                }
            }
            while ($current->lte($rangeEnd) && $current->lte($seriesEnd)) {
                $occurrences[] = $this->occurrencePayload($schedule, $current);
                $current->addDays($interval);
            }
            return $occurrences;
        }

        if ($frequency === 'monthly') {
            $dayOfMonth = $seriesStart->day;
            $current = $seriesStart->copy()->startOfMonth();
            $endMonth = $seriesEnd->copy()->startOfMonth();

            while ($current->lte($endMonth)) {
                $monthsDiff = $seriesStart->diffInMonths($current);
                if ($monthsDiff % $interval === 0) {
                    $daysInMonth = $current->daysInMonth;
                    if ($dayOfMonth <= $daysInMonth) {
                        $occurrenceDate = $current->copy()->day($dayOfMonth);
                        if (
                            $occurrenceDate->betweenIncluded($rangeStart, $rangeEnd) &&
                            $occurrenceDate->gte($seriesStart) &&
                            $occurrenceDate->lte($seriesEnd)
                        ) {
                            $occurrences[] = $this->occurrencePayload($schedule, $occurrenceDate);
                        }
                    }
                }
                $current->addMonths($interval);
            }
            return $occurrences;
        }

        $byweekday = $schedule->recurrence_byweekday ?: [$seriesStart->dayOfWeekIso];
        $byweekday = array_map('intval', $byweekday);
        $weekStart = $seriesStart->copy()->startOfWeek(Carbon::MONDAY);
        $current = $rangeStart->copy();
        while ($current->lte($rangeEnd) && $current->lte($seriesEnd)) {
            $weekDiff = intdiv($weekStart->diffInDays($current->copy()->startOfWeek(Carbon::MONDAY)), 7);
            if ($weekDiff % $interval === 0 && in_array($current->dayOfWeekIso, $byweekday, true)) {
                if ($current->gte($seriesStart)) {
                    $occurrences[] = $this->occurrencePayload($schedule, $current);
                }
            }
            $current->addDay();
        }

        return $occurrences;
    }

    protected function occurrencePayload(Schedule $schedule, Carbon $date): array
    {
        return [
            'id' => $schedule->id,
            'occurrence_id' => $schedule->id . '|' . $date->toDateString(),
            'date' => $date->toDateString(),
            'series_date' => $schedule->date?->toDateString(),
            'start_time' => $schedule->start_time,
            'end_time' => $schedule->end_time,
            'title' => $schedule->title,
            'location' => $schedule->location,
            'thumbnail_url' => $this->publicStorageUrl($schedule->thumbnail_url),
            'notes' => $schedule->notes,
            'is_recurring' => $schedule->is_recurring,
            'recurrence_frequency' => $schedule->recurrence_frequency,
            'recurrence_interval' => $schedule->recurrence_interval,
            'recurrence_byweekday' => $schedule->recurrence_byweekday,
            'recurrence_until' => $schedule->recurrence_until?->toDateString(),
            'assignees' => $schedule->assignees->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->values(),
        ];
    }

    protected function publicStorageUrl(?string $value): ?string
    {
        if (! $value) {
            return $value;
        }

        $publicPrefix = '/storage/';
        $path = null;

        if (str_starts_with($value, $publicPrefix)) {
            $path = substr($value, strlen($publicPrefix));
        } else {
            $configuredPrefix = rtrim(config('filesystems.disks.public.url'), '/').'/';
            if (str_starts_with($value, $configuredPrefix)) {
                $path = substr($value, strlen($configuredPrefix));
            } else {
                $urlPath = parse_url($value, PHP_URL_PATH);
                if (is_string($urlPath) && str_starts_with($urlPath, $publicPrefix)) {
                    $path = substr($urlPath, strlen($publicPrefix));
                }
            }
        }

        if (! $path) {
            return $value;
        }

        $asset = MediaAsset::query()->where('path', ltrim($path, '/'))->first();

        return $asset ? route('media.assets.show', $asset) : url($publicPrefix.ltrim($path, '/'));
    }
}
