<?php

namespace App\Http\Controllers;

use App\Models\{MovementRoster, MovementDelegate, MovementJourney, MovementScan};
use App\Services\DelegateImport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class DelegateMovementController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'rosters' => MovementRoster::orderByDesc('id')->get(),
            'organisation_id' => $request->attributes->get('organisation')->id,
            'user_id' => $request->user()->id,
        ]);
    }

    public function store(Request $request)
    {
        return response()->json(MovementRoster::create($request->validate(['name' => 'required|string|max:120'])), 201);
    }

    public function show(MovementRoster $roster)
    {
        return response()->json([
            'roster' => $roster,
            // Keep contact information off offline devices and QR badges.
            'delegates' => MovementDelegate::where('roster_id', $roster->id)->orderBy('name')->get(['id', 'name', 'group', 'reference']),
            'journeys' => MovementJourney::where('roster_id', $roster->id)->orderByDesc('id')->get(),
            'scans' => MovementScan::whereIn('delegate_id', MovementDelegate::where('roster_id', $roster->id)->select('id'))->orderBy('id')->get(),
            'synced_at' => now()->toIso8601String(),
        ]);
    }

    public function import(Request $request, MovementRoster $roster, DelegateImport $importer)
    {
        $request->validate(['file' => 'required|file|max:5120']);
        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        abort_unless(in_array($extension, ['csv', 'xlsx']), 422, 'Upload an XLSX or CSV file.');
        $rows = $importer->read($file->getRealPath(), $extension === 'xlsx');
        return response()->json(['imported' => $importer->import($roster, $rows)]);
    }

    public function journey(Request $request, MovementRoster $roster)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120', 'kind' => 'required|in:bus,checkpoint',
            'location' => 'required|string|max:120',
            'expected_ids' => 'required|array|min:1|max:2000',
            'expected_ids.*' => 'required|integer|distinct',
        ]);
        $valid = MovementDelegate::where('roster_id', $roster->id)->whereIn('id', $data['expected_ids'])->count();
        abort_unless($valid === count($data['expected_ids']), 422, 'Select delegates from this roster.');
        return response()->json(MovementJourney::create([...$data, 'roster_id' => $roster->id]), 201);
    }

    public function sync(Request $request, MovementRoster $roster)
    {
        $data = $request->validate([
            'events' => 'required|array|min:1|max:100',
            'events.*.event_id' => 'required|uuid|distinct',
            'events.*.delegate_id' => 'required|integer',
            'events.*.journey_id' => 'required|integer',
            'events.*.base_event_id' => 'nullable|uuid',
            'events.*.action' => 'required|in:board,exit,arrive,depart',
            'events.*.stop' => 'required|string|max:120',
            'events.*.recorded_at' => 'required|date',
        ]);
        $results = [];
        foreach ($data['events'] as $event) {
            $event = Arr::only($event, ['event_id', 'delegate_id', 'journey_id', 'base_event_id', 'action', 'stop', 'recorded_at']);
            try {
                $scan = DB::transaction(function () use ($event, $roster, $request) {
                    $delegate = MovementDelegate::where('roster_id', $roster->id)->lockForUpdate()->find($event['delegate_id']);
                    if (! $delegate) $this->conflict('Delegate is not in this roster.');
                    $existing = MovementScan::where('event_id', $event['event_id'])->first();
                    if ($existing) {
                        foreach (['delegate_id', 'journey_id', 'action', 'stop', 'base_event_id'] as $field) {
                            if (($existing->$field ?? null) != ($event[$field] ?? null)) $this->conflict('Scan identifier was already used for a different movement.');
                        }
                        return $existing; // Idempotent retry after a lost network response.
                    }
                    $journey = MovementJourney::where('roster_id', $roster->id)->find($event['journey_id']);
                    if (! $journey || ! in_array($delegate->id, $journey->expected_ids)) $this->conflict('Delegate is not expected on this journey.');
                    $last = MovementScan::where('delegate_id', $delegate->id)->latest('id')->first();
                    if (($last?->event_id ?? null) !== ($event['base_event_id'] ?? null)) $this->conflict('Another device recorded a movement. Review the latest history before scanning again.');
                    $at = Carbon::parse($event['recorded_at']);
                    if ($at->gt(now()->addMinutes(5))) $this->conflict('Device time is in the future. Correct its clock before recording.');
                    if ($last && $at->lt($last->recorded_at)) $this->conflict('Movement predates the latest record. Review the history and device clock.');
                    if ($journey->kind === 'bus') {
                        if (! in_array($event['action'], ['board', 'exit'])) $this->conflict('Choose board or exit for a bus journey.');
                        $bus = MovementScan::where('delegate_id', $delegate->id)->whereIn('action', ['board', 'exit'])->latest('id')->first();
                        if ($event['action'] === 'board' && $bus?->action === 'board') $this->conflict('Already recorded on a bus. Record an exit first.');
                        if ($event['action'] === 'exit' && (! $bus || $bus->action !== 'board' || $bus->journey_id !== $journey->id)) $this->conflict('No boarding record for this bus journey.');
                    } else {
                        if (! in_array($event['action'], ['arrive', 'depart'])) $this->conflict('Choose arrive or depart for a checkpoint.');
                        $previous = MovementScan::where('delegate_id', $delegate->id)->where('journey_id', $journey->id)->latest('id')->first();
                        if ($event['action'] === 'arrive' && $previous?->action === 'arrive') $this->conflict('Already checked into this checkpoint.');
                        if ($event['action'] === 'depart' && $previous?->action !== 'arrive') $this->conflict('Record an arrival before departure.');
                    }
                    return MovementScan::create([...$event, 'recorded_at' => $at->utc(),
                        'recorded_by' => $request->user()->id, 'received_at' => now()]);
                }, 3);
                $results[] = ['event_id' => $event['event_id'], 'status' => 'accepted', 'scan' => $scan];
            } catch (ValidationException $exception) {
                $results[] = ['event_id' => $event['event_id'], 'status' => 'conflict', 'message' => $exception->getMessage()];
            }
        }
        return response()->json(['results' => $results]);
    }

    private function conflict(string $message): never
    {
        throw ValidationException::withMessages(['movement' => $message]);
    }
}
