import { test } from 'node:test';
import assert from 'node:assert/strict';
import { effectiveScans, movementProblem } from '../resources/js/composables/delegateMovementStore.js';

const bus = { id: 10, kind: 'bus', expected_ids: [1] };
test('an offline boarding is included in headcounts and prevents boarding a second bus', () => {
    const state = { pending: [{ event_id: 'one', roster_id: 2, delegate_id: 1, journey_id: 10, action: 'board' }] };
    const scans = effectiveScans({ roster: { id: 2 }, scans: [] }, state);
    assert.equal(scans.length, 1);
    assert.match(movementProblem(scans, bus, 1, 'board'), /Already on a bus/);
    assert.match(movementProblem(scans, { ...bus, id: 11 }, 1, 'board'), /Already on a bus/);
    assert.equal(movementProblem(scans, bus, 1, 'exit'), '');
});
test('network retries do not count a saved event twice and other rosters stay isolated', () => {
    const accepted = { event_id: 'one', roster_id: 2, delegate_id: 1, journey_id: 10, action: 'board' };
    const scans = effectiveScans({ roster: { id: 2 }, scans: [accepted] }, { pending: [accepted, { event_id: 'other', roster_id: 3 }] });
    assert.equal(scans.length, 1);
});
test('offline checkpoints and expected passenger lists enforce valid movements', () => {
    assert.match(movementProblem([], bus, 2, 'board'), /not expected/);
    assert.match(movementProblem([], bus, 1, 'exit'), /No boarding/);
    const checkpoint = { ...bus, kind: 'checkpoint' };
    assert.match(movementProblem([], checkpoint, 1, 'depart'), /arrival/);
    assert.equal(movementProblem([], checkpoint, 1, 'arrive'), '');
    assert.match(movementProblem([{ delegate_id: 1, journey_id: 10, action: 'arrive' }], checkpoint, 1, 'arrive'), /Already/);
});
