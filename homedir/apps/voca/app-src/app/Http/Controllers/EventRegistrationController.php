<?php

namespace App\Http\Controllers;

use App\Models\EventRegistration;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventRegistrationController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'occurrence_id' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'party_size' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $event = $this->findOccurrence($data['occurrence_id']);
        abort_unless($event, 404, 'This event could not be found.');

        $token = Str::random(64);

        try {
            $registration = EventRegistration::create([
                ...$data,
                'email' => Str::lower($data['email']),
                'schedule_id' => $event['schedule_id'],
                'event_title' => $event['title'],
                'event_date' => $event['date'],
                'event_start_time' => $event['start_time'],
                'event_location' => $event['location'],
                'reference' => $this->uniqueReference(),
                'ticket_token_hash' => hash('sha256', $token),
            ]);
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23000', '23505'], true)) {
                return response()->json([
                    'message' => 'This email address is already registered for the event.',
                ], 422);
            }

            throw $exception;
        }

        return response()->json([
            'registration' => $this->ticketPayload($registration),
            'ticket_token' => $token,
        ], 201);
    }

    public function ticket(string $token)
    {
        $registration = EventRegistration::where('ticket_token_hash', hash('sha256', $token))->firstOrFail();

        return response()->json(['registration' => $this->ticketPayload($registration)]);
    }

    public function index(Request $request)
    {
        $this->authorizeLeader($request);
        $data = $request->validate([
            'date' => ['nullable', 'date'],
            'occurrence_id' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = EventRegistration::query()->latest('created_at');

        if (! empty($data['date'])) {
            $query->whereDate('event_date', $data['date']);
        }
        if (! empty($data['occurrence_id'])) {
            $query->where('occurrence_id', $data['occurrence_id']);
        }
        if (! empty($data['search'])) {
            $search = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $data['search']).'%';
            $query->where(fn ($q) => $q
                ->where('name', 'like', $search)
                ->orWhere('email', 'like', $search)
                ->orWhere('reference', 'like', $search));
        }

        $registrations = $query->limit(500)->get();

        return response()->json([
            'registrations' => $registrations,
            'summary' => [
                'registrations' => $registrations->count(),
                'guests' => $registrations->sum('party_size'),
                'checked_in' => $registrations->whereNotNull('checked_in_at')->sum('party_size'),
            ],
        ]);
    }

    public function checkIn(Request $request)
    {
        $this->authorizeLeader($request);
        $data = $request->validate(['code' => ['required', 'string', 'max:1000']]);
        $credential = $this->extractCredential($data['code']);

        $registration = strlen($credential) === 16
            ? EventRegistration::where('reference', Str::upper($credential))->first()
            : EventRegistration::where('ticket_token_hash', hash('sha256', $credential))->first();

        if (! $registration) {
            return response()->json(['message' => 'Ticket not recognised.'], 404);
        }

        if ($registration->checked_in_at) {
            return response()->json([
                'message' => 'This ticket was already checked in.',
                'registration' => $registration,
                'already_checked_in' => true,
            ], 409);
        }

        $registration->update([
            'checked_in_at' => now(),
            'checked_in_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => "{$registration->name} checked in successfully.",
            'registration' => $registration->fresh(),
        ]);
    }

    private function findOccurrence(string $occurrenceId): ?array
    {
        $scheduleId = (int) Str::before($occurrenceId, '|');
        $schedule = Schedule::with('ministry')->find($scheduleId);
        if (! $schedule) {
            return null;
        }

        $start = now()->subDay()->startOfDay();
        $end = now()->addYear()->endOfDay();
        $occurrences = app(ScheduleController::class)->expandSchedule($schedule, $start, $end);
        $occurrence = collect($occurrences)->firstWhere('occurrence_id', $occurrenceId);

        return $occurrence ? [...$occurrence, 'schedule_id' => $schedule->id] : null;
    }

    private function uniqueReference(): string
    {
        do {
            $reference = 'PH-'.Str::upper(Str::random(12));
        } while (EventRegistration::where('reference', $reference)->exists());

        return $reference;
    }

    private function extractCredential(string $code): string
    {
        $code = trim($code);
        if (filter_var($code, FILTER_VALIDATE_URL)) {
            $path = parse_url($code, PHP_URL_PATH);
            return (string) Str::afterLast($path, '/');
        }

        return Str::startsWith($code, 'PH-TICKET:') ? Str::after($code, 'PH-TICKET:') : $code;
    }

    private function authorizeLeader(Request $request): void
    {
        $user = $request->user();
        $allowed = $user?->is_superadmin || $user?->organisationMemberships()->where('organisation_id', $request->attributes->get('organisation')?->id)->where('status', 'active')->whereIn('role', ['owner', 'admin'])->exists() || $user?->memberships()
            ->where(function ($query) {
                $query->whereIn('role', ['leader', 'admin'])
                    ->orWhereHas('roleRecord', fn ($role) => $role->whereIn('slug', ['leader', 'admin']));
            })
            ->exists();
        abort_unless($allowed, 403, 'Leader access is required.');
    }

    private function ticketPayload(EventRegistration $registration): array
    {
        return [
            'reference' => $registration->reference,
            'name' => $registration->name,
            'party_size' => $registration->party_size,
            'event_title' => $registration->event_title,
            'event_date' => $registration->event_date->format('Y-m-d'),
            'event_start_time' => $registration->event_start_time,
            'event_location' => $registration->event_location,
            'checked_in_at' => $registration->checked_in_at,
        ];
    }
}
