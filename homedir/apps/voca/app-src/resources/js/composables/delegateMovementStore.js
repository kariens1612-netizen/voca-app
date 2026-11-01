const GRANT = 'voca-movement-access';
const DATABASE = 'voca-delegate-movements-v1';
const empty = () => ({ rosters: [], snapshots: {}, pending: [], conflicts: [] });

export function offlineMovementAccess() {
    try {
        const grant = JSON.parse(sessionStorage.getItem(GRANT));
        return grant?.expires > Date.now() && grant.api === (window.axios.defaults.baseURL || location.origin) ? grant : null;
    } catch { return null; }
}
export function grantMovementAccess(data) {
    const grant = { user: data.user_id, organisation: data.organisation_id,
        api: window.axios.defaults.baseURL || location.origin, expires: Date.now() + 12 * 3600000 };
    sessionStorage.setItem(GRANT, JSON.stringify(grant));
    return grant;
}
export function revokeMovementAccess() { sessionStorage.removeItem(GRANT); }
export function movementCacheKey(grant) { return `${grant.api}:${grant.organisation}:${grant.user}`; }

let database;
function openDatabase() {
    if (!database) database = new Promise((resolve, reject) => {
        const request = indexedDB.open(DATABASE, 1);
        request.onupgradeneeded = () => request.result.createObjectStore('states');
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(new Error('Offline storage is unavailable. Enable browser storage before scanning.'));
    });
    return database;
}
// Each read/modify/write is one transaction, including across tabs.
export async function movementStore(key, change) {
    const db = await openDatabase();
    return new Promise((resolve, reject) => {
        const tx = db.transaction('states', change ? 'readwrite' : 'readonly');
        const store = tx.objectStore('states');
        const get = store.get(key);
        let value;
        let failure;
        get.onsuccess = () => {
            value = get.result || empty();
            try { if (change) { change(value); store.put(value, key); } }
            catch (error) { failure = error; tx.abort(); }
        };
        tx.oncomplete = () => resolve(value);
        tx.onabort = tx.onerror = () => reject(failure || new Error('Could not save on this device. No scan was recorded.'));
    });
}

export function effectiveScans(snapshot, state) {
    const ids = new Set((snapshot?.scans || []).map(scan => scan.event_id));
    return [...(snapshot?.scans || []), ...state.pending.filter(scan =>
        scan.roster_id === snapshot?.roster?.id && !ids.has(scan.event_id))];
}
export function movementProblem(scans, journey, delegateId, action) {
    if (!journey?.expected_ids.includes(delegateId)) return 'This delegate is not expected on the selected journey.';
    const mine = scans.filter(scan => scan.delegate_id === delegateId);
    if (journey.kind === 'bus') {
        const bus = mine.filter(scan => ['board', 'exit'].includes(scan.action)).at(-1);
        if (action === 'board' && bus?.action === 'board') return 'Already on a bus. Record an exit first.';
        if (action === 'exit' && (bus?.action !== 'board' || bus.journey_id !== journey.id)) return 'No boarding record for this bus journey.';
    } else {
        const previous = mine.filter(scan => scan.journey_id === journey.id).at(-1);
        if (action === 'arrive' && previous?.action === 'arrive') return 'Already at this checkpoint.';
        if (action === 'depart' && previous?.action !== 'arrive') return 'Record arrival before departure.';
    }
    return '';
}
