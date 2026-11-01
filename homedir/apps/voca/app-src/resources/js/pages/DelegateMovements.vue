<template>
    <main class="movement-page">
        <section class="event-portal-hero"><div class="container">
            <p class="eyebrow">Delegate operations</p><h1>Every journey. Everyone accounted for.</h1>
            <p>Scan a badge, check the headcount, and keep your group moving.</p>
            <div class="movement-actions"><span class="movement-pill">{{ online ? 'Connected' : 'Offline · saving on this phone' }}</span>
                <span class="movement-pill">{{ state.pending.length }} pending · {{ state.conflicts.length }} to review</span>
                <button class="button primary" :disabled="busy || !online" @click="sync">{{ busy ? 'Syncing…' : 'Sync now' }}</button>
            </div>
            <p class="movement-caption">{{ snapshot?.synced_at ? `Last refreshed ${formatTime(snapshot.synced_at)}` : 'Download a roster while connected before travelling.' }} · Headcounts include this device’s pending scans. Other phones may have unsynced records.</p>
        </div></section>
        <section class="section"><div class="container">
            <p v-if="message" class="scan-result" :class="messageKind" role="status" aria-live="polite">{{ message }}</p>
            <div class="movement-topbar">
                <label>Delegate roster<select v-model="rosterId" :disabled="busy" @change="selectRoster"><option value="">Select a roster</option><option v-for="roster in state.rosters" :key="roster.id" :value="roster.id">{{ roster.name }}</option></select></label>
                <form class="movement-inline" @submit.prevent="createRoster"><label>New roster<input v-model.trim="newRoster" maxlength="120" required placeholder="VOCA delegates" /></label><button class="button ghost" :disabled="!online || busy">Create roster</button></form>
            </div>
            <template v-if="snapshot">
                <details class="movement-setup"><summary>Manage delegates, journeys and badges</summary>
                    <div class="movement-setup-grid">
                        <div><h2>Import delegates</h2><p>Upload the supplied Google Contacts XLSX or a CSV with Name, Email, Phone and Group columns. Existing emails in this roster are skipped.</p>
                            <input ref="fileInput" type="file" accept=".xlsx,.csv" aria-label="Delegate spreadsheet" :disabled="!online || busy" @change="importDelegates" />
                            <p>{{ snapshot.delegates.length }} delegates · QR badges contain a random reference, without contact details.</p>
                            <button class="button ghost" :disabled="!snapshot.delegates.length" @click="prepareBadges">Prepare printable badges</button>
                        </div>
                        <form class="movement-form" @submit.prevent="createJourney"><h2>Add a journey or checkpoint</h2>
                            <label>Name<input v-model.trim="journeyForm.name" required maxlength="120" placeholder="Hotel to conference · 27 October" /></label>
                            <label>Type<select v-model="journeyForm.kind"><option value="bus">Bus journey</option><option value="checkpoint">Venue / checkpoint</option></select></label>
                            <label>Bus or checkpoint<input v-model.trim="journeyForm.location" required maxlength="120" placeholder="Bus 1 / Conference hall" /></label>
                            <label><span>Expected delegates ({{ expectedIds.length }})</span><select v-model="expectedIds" multiple size="5" required><option v-for="delegate in snapshot.delegates" :key="delegate.id" :value="delegate.id">{{ delegate.name }}</option></select></label>
                            <div class="movement-actions"><button class="button ghost" type="button" @click="expectedIds = snapshot.delegates.map(d => d.id)">Select everyone</button><button class="button primary" :disabled="!online || busy">Create</button></div>
                        </form>
                    </div>
                </details>
                <div v-if="state.conflicts.length" class="movement-conflicts"><h2>Movements needing review</h2><p>These records are excluded from the headcount. Review history, dismiss the saved record, then record the correct movement if needed.</p>
                    <article v-for="conflict in state.conflicts" :key="conflict.event_id"><div><strong>{{ delegateName(conflict.delegate_id) }} · {{ conflict.action }}</strong><p>{{ conflict.message }}</p><small>{{ formatTime(conflict.recorded_at) }} · {{ conflict.stop }}</small></div><button class="button ghost" @click="dismissConflict(conflict)">Dismiss saved record</button></article>
                </div>
                <div class="event-portal-layout">
                    <section class="scanner-panel movement-form"><h2>Record a movement</h2>
                        <label>Journey / checkpoint<select v-model="journeyId" @change="changeJourney"><option value="">Select a journey</option><option v-for="item in snapshot.journeys" :key="item.id" :value="item.id">{{ item.name }} · {{ item.location }}</option></select></label>
                        <template v-if="journey">
                            <div class="movement-action-picker"><button v-for="item in availableActions" :key="item.value" class="button" :class="action === item.value ? 'primary' : 'ghost'" @click="action = item.value">{{ item.label }}</button></div>
                            <label>Current stop<input v-model.trim="stop" maxlength="120" placeholder="Hotel entrance / Venue" /></label>
                            <video v-show="scanning" ref="video" class="scanner-viewport" muted playsinline></video>
                            <button v-if="!scanning" class="button primary" :disabled="!stop" @click="startCamera">Scan QR badge</button><button v-else class="button ghost" @click="stopCamera">Stop camera</button>
                            <form class="manual-checkin" @submit.prevent="recordCode(code)"><label>Or enter badge reference<input v-model.trim="code" placeholder="VOCA-…" required /></label><button class="button ghost">Record {{ actionLabel }}</button></form>
                            <p class="movement-caption">Hold one badge in view at a time. A scan records the action selected above. You can also find a delegate by name.</p>
                        </template>
                        <p v-else>Create or select a journey to start scanning.</p>
                    </section>
                    <section class="event-attendees">
                        <div class="portal-stats"><div><strong>{{ expected.length }}</strong><span>Expected</span></div><div><strong>{{ presentIds.size }}</strong><span>{{ journey?.kind === 'checkpoint' ? 'At checkpoint' : 'On this bus' }}</span></div><div><strong>{{ outstanding.length }}</strong><span>Still expected</span></div></div>
                        <div class="attendee-toolbar"><h2>Headcount</h2><input v-model="search" type="search" placeholder="Find a delegate or group" aria-label="Find delegate" /></div>
                        <div class="movement-actions"><label>Show<select v-model="filter"><option value="all">All expected</option><option value="outstanding">Still expected</option><option value="present">Currently present</option></select></label><button class="button ghost" :disabled="!journey" @click="exportManifest">Export manifest</button></div>
                        <div class="attendee-list">
                            <article v-for="delegate in visibleDelegates" :key="delegate.id" class="attendee-row"><div><strong>{{ delegate.name }}</strong><span>{{ delegate.group || 'Delegate' }}</span><small>{{ status(delegate.id) }}</small></div>
                                <div class="movement-actions"><button class="button ghost" :disabled="!journey || !stop" @click="recordCode(delegate.reference)">{{ actionLabel }}</button><button class="button ghost" @click="historyId = delegate.id">History</button></div>
                            </article><p v-if="!visibleDelegates.length">No delegates match this view.</p>
                        </div>
                    </section>
                </div>
                <section v-if="historyId" class="movement-history"><div class="movement-actions"><h2>{{ delegateName(historyId) }} · movement history</h2><button class="button ghost" @click="historyId = null">Close</button></div>
                    <p v-if="!history.length">No movements recorded.</p><ol><li v-for="scan in history" :key="scan.event_id"><strong>{{ scan.action }} · {{ scan.stop }}</strong> — {{ formatTime(scan.recorded_at) }}<span> · {{ journeyName(scan.journey_id) }} · {{ scan.id ? 'Synced' : 'Pending on this phone' }}</span></li></ol>
                </section>
                <section v-if="badges.length" class="movement-badges"><div class="movement-actions movement-no-print"><h2>Delegate QR badges</h2><button class="button primary" @click="printBadges">Print / save PDF</button><button class="button ghost" @click="badges = []">Close badges</button></div><div class="movement-badge-grid"><article v-for="badge in badges" :key="badge.id" class="movement-badge"><strong>VOCA · DELEGATE</strong><h2>{{ badge.name }}</h2><img :src="badge.qr" alt="Delegate QR badge" /><small>{{ badge.reference }}</small></article></div></section>
            </template>
            <p v-else-if="!busy">Create a roster and import your delegates to begin. Only organisation administrators can access this portal.</p>
        </div></section>
    </main>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { BrowserQRCodeReader } from '@zxing/browser';
import QRCode from 'qrcode';
import { Capacitor } from '@capacitor/core';
import { Camera } from '@capacitor/camera';
import { offlineMovementAccess, grantMovementAccess, revokeMovementAccess, movementCacheKey, movementStore, effectiveScans, movementProblem } from '../composables/delegateMovementStore';

const state = ref({ rosters: [], snapshots: {}, pending: [], conflicts: [] });
const rosterId = ref(''), journeyId = ref(''), action = ref('board'), stop = ref(''), search = ref(''), filter = ref('all');
const online = ref(navigator.onLine), busy = ref(false), message = ref(''), messageKind = ref('success');
const newRoster = ref(''), expectedIds = ref([]), historyId = ref(null), badges = ref([]), fileInput = ref(null);
const journeyForm = ref({ name: '', kind: 'bus', location: '' });
const video = ref(null), scanning = ref(false), code = ref('');
let key, controls, scanLocked = false, disposed = false, timer, refreshTimer;
const endpoint = '/api/delegate-movements';
const snapshot = computed(() => state.value.snapshots[rosterId.value]);
const journey = computed(() => snapshot.value?.journeys.find(item => item.id === Number(journeyId.value)));
const scans = computed(() => effectiveScans(snapshot.value, state.value));
const expected = computed(() => snapshot.value?.delegates.filter(d => journey.value?.expected_ids.includes(d.id)) || []);
const presentIds = computed(() => {
    const latest = new Map();
    for (const scan of scans.value) if (scan.journey_id === journey.value?.id) latest.set(scan.delegate_id, scan);
    return new Set([...latest].filter(([, scan]) => ['board', 'arrive'].includes(scan.action)).map(([id]) => id));
});
const outstanding = computed(() => expected.value.filter(d => !presentIds.value.has(d.id)));
const visibleDelegates = computed(() => expected.value.filter(d =>
    `${d.name} ${d.group || ''}`.toLowerCase().includes(search.value.toLowerCase()) &&
    (filter.value === 'all' || (filter.value === 'present' ? presentIds.value.has(d.id) : !presentIds.value.has(d.id)))));
const availableActions = computed(() => journey.value?.kind === 'checkpoint'
    ? [{ value: 'arrive', label: 'Arrive' }, { value: 'depart', label: 'Depart' }]
    : [{ value: 'board', label: 'Board bus' }, { value: 'exit', label: 'Exit bus' }]);
const actionLabel = computed(() => availableActions.value.find(item => item.value === action.value)?.label || 'Record');
const history = computed(() => scans.value.filter(scan => scan.delegate_id === historyId.value).slice().reverse());
const formatTime = value => new Date(value).toLocaleString();
const delegateName = id => snapshot.value?.delegates.find(d => d.id === id)?.name || `Delegate ${id}`;
const journeyName = id => snapshot.value?.journeys.find(j => j.id === id)?.name || `Journey ${id}`;
const tell = (text, kind = 'success') => { message.value = text; messageKind.value = kind; };
const errorMessage = error => error.response?.data?.message || error.message || 'Could not complete this action.';
function status(id) {
    const latest = scans.value.filter(scan => scan.delegate_id === id).at(-1);
    const bus = scans.value.filter(scan => scan.delegate_id === id && ['board', 'exit'].includes(scan.action)).at(-1);
    if (bus?.action === 'board' && bus.journey_id !== journey.value?.id) return `On another bus: ${journeyName(bus.journey_id)}`;
    return latest ? `${latest.action} · ${latest.stop} · ${formatTime(latest.recorded_at)}${latest.id ? '' : ' · pending'}` : 'No movement recorded';
}
async function update(change) { state.value = await movementStore(key, change); }
async function refreshRoster(id) {
    const { data } = await window.axios.get(`${endpoint}/${id}`, { timeout: 15000 });
    await update(value => { value.snapshots[id] = data; });
}
async function selectRoster() {
    stopCamera(); badges.value = []; historyId.value = null; journeyId.value = '';
    if (online.value && rosterId.value) {
        try { await refreshRoster(rosterId.value); } catch (error) { tell(errorMessage(error), 'error'); }
    }
    expectedIds.value = snapshot.value?.delegates.map(d => d.id) || [];
    journeyId.value = snapshot.value?.journeys[0]?.id || '';
    changeJourney();
}
function changeJourney() {
    action.value = journey.value?.kind === 'checkpoint' ? 'arrive' : 'board';
    stop.value = journey.value?.location || '';
}
async function createRoster() {
    try {
        const { data } = await window.axios.post(endpoint, { name: newRoster.value });
        await update(value => { value.rosters.unshift(data); });
        rosterId.value = data.id; newRoster.value = ''; await selectRoster();
    } catch (error) { tell(errorMessage(error), 'error'); }
}
async function importDelegates(event) {
    const file = event.target.files[0]; if (!file) return;
    const id = rosterId.value;
    busy.value = true;
    try {
        const form = new FormData(); form.append('file', file);
        const { data } = await window.axios.post(`${endpoint}/${id}/import`, form);
        await refreshRoster(id); expectedIds.value = snapshot.value.delegates.map(d => d.id);
        tell(`${data.imported} delegates imported. Existing journeys keep their original expected lists; create a new journey to include new delegates.`);
    } catch (error) { tell(errorMessage(error), 'error'); }
    finally { busy.value = false; if (fileInput.value) fileInput.value.value = ''; }
}
async function createJourney() {
    try {
        const { data } = await window.axios.post(`${endpoint}/${rosterId.value}/journeys`, { ...journeyForm.value, expected_ids: expectedIds.value });
        await refreshRoster(rosterId.value); journeyId.value = data.id; changeJourney(); tell('Journey ready. Refresh every marshal’s phone before leaving.');
    } catch (error) { tell(errorMessage(error), 'error'); }
}
async function recordCode(reference) {
    if (!snapshot.value || !journey.value || !stop.value) return tell('Select a journey and enter the current stop.', 'warning');
    const grant = offlineMovementAccess();
    if (!grant || movementCacheKey(grant) !== key) return tell('Offline access expired or account changed. Reconnect and reopen this portal before recording more movements.', 'error');
    const delegate = snapshot.value.delegates.find(d => d.reference.toUpperCase() === reference.trim().toUpperCase());
    if (!delegate) return tell('Badge not recognised in this roster. Search by name or check the selected roster.', 'error');
    const selected = { roster: Number(rosterId.value), journey: journey.value.id, action: action.value, stop: stop.value };
    try {
        await update(value => {
            if (value.conflicts.some(scan => scan.delegate_id === delegate.id)) throw new Error('Review this delegate’s conflicting scan before recording another movement.');
            const current = effectiveScans(value.snapshots[selected.roster], value);
            const problem = movementProblem(current, value.snapshots[selected.roster].journeys.find(j => j.id === selected.journey), delegate.id, selected.action);
            if (problem) throw new Error(problem);
            value.pending.push({ event_id: crypto.randomUUID(), roster_id: selected.roster, delegate_id: delegate.id,
                journey_id: selected.journey, action: selected.action, stop: selected.stop,
                base_event_id: current.filter(scan => scan.delegate_id === delegate.id).at(-1)?.event_id || null,
                recorded_at: new Date().toISOString() });
        });
        tell(`${delegate.name} · ${actionLabel.value} · saved on this phone${online.value ? ', syncing…' : ''}`);
        code.value = ''; navigator.vibrate?.(100);
        if (online.value) void sync();
    } catch (error) { tell(errorMessage(error), 'warning'); }
}
async function sync() {
    if (busy.value || !key || !online.value) return;
    busy.value = true;
    const run = async () => {
        // Revalidate the user and tenant before uploading any saved records.
        const { data } = await window.axios.get(endpoint, { timeout: 15000 });
        const grant = grantMovementAccess(data);
        if (movementCacheKey(grant) !== key) throw new Error('Account or organisation changed. Reopen the portal before syncing.');
        await update(value => { value.rosters = data.rosters; });
        state.value = await movementStore(key);
        for (const id of [...new Set(state.value.pending.map(scan => scan.roster_id))]) {
            // Batches preserve dependency order for offline boarding and exits.
            while (true) {
                const current = await movementStore(key);
                const batch = current.pending.filter(scan => scan.roster_id === id).slice(0, 100);
                if (!batch.length) break;
                const { data: result } = await window.axios.post(`${endpoint}/${id}/sync`, { events: batch }, { timeout: 20000 });
                await update(value => {
                    for (const item of result.results) {
                        const saved = value.pending.find(scan => scan.event_id === item.event_id);
                        if (!saved) continue;
                        if (item.status === 'accepted') {
                            const list = value.snapshots[id]?.scans;
                            if (list && !list.some(scan => scan.event_id === item.event_id)) list.push(item.scan);
                        } else value.conflicts.push({ ...saved, message: item.message });
                        value.pending = value.pending.filter(scan => scan.event_id !== item.event_id);
                    }
                });
            }
            await refreshRoster(id);
        }
        if (rosterId.value) await refreshRoster(rosterId.value);
        if (state.value.conflicts.length) tell('Sync completed with conflicts. Review the flagged movements before relying on the headcount.', 'warning');
        else if (!state.value.pending.length) tell('All saved movements synced. Headcounts refreshed.');
    };
    try {
        if (navigator.locks) await navigator.locks.request(`movement-sync:${key}`, run);
        else await run();
    } catch (error) {
        if ([401, 403, 419].includes(error.response?.status)) revokeMovementAccess();
        tell(`${errorMessage(error)} Pending scans remain saved on this device.`, 'error');
    }
    finally { busy.value = false; }
}
async function dismissConflict(conflict) {
    try { await update(value => { value.conflicts = value.conflicts.filter(scan => scan.event_id !== conflict.event_id); }); }
    catch (error) { tell(errorMessage(error), 'error'); }
}
async function prepareBadges() {
    try {
        badges.value = await Promise.all(snapshot.value.delegates.map(async d => ({ ...d, qr: await QRCode.toDataURL(d.reference, { width: 240, margin: 2 }) })));
        await nextTick(); document.querySelector('.movement-badges')?.scrollIntoView({ behavior: 'smooth' });
    } catch (error) { tell(errorMessage(error), 'error'); }
}
const printBadges = () => window.print();
function exportManifest() {
    const cell = value => `"${String(value ?? '').replace(/^[=+@\-\t\r]/, "'$&").replaceAll('"', '""')}"`;
    const rows = [['Delegate', 'Journey', 'Bus / checkpoint', 'Current status', 'Pending on device', 'Last refreshed'],
        ...expected.value.map(d => [d.name, journey.value.name, journey.value.location, status(d.id), state.value.pending.some(scan => scan.delegate_id === d.id) ? 'Yes' : 'No', snapshot.value.synced_at])];
    const url = URL.createObjectURL(new Blob(['\uFEFF' + rows.map(row => row.map(cell).join(',')).join('\r\n')], { type: 'text/csv;charset=utf-8' }));
    const link = document.createElement('a'); link.href = url; link.download = `voca-manifest-${journey.value.id}.csv`; link.click(); setTimeout(() => URL.revokeObjectURL(url), 1000);
}
async function startCamera() {
    scanning.value = true; await nextTick();
    try {
        if (Capacitor.isNativePlatform()) {
            const permission = await Camera.requestPermissions({ permissions: ['camera'] });
            if (permission.camera !== 'granted') throw new Error('Camera permission denied.');
        }
        const reader = new BrowserQRCodeReader();
        const session = await reader.decodeFromConstraints({ video: { facingMode: { ideal: 'environment' } }, audio: false }, video.value, async result => {
            if (!result || scanLocked || !scanning.value) return;
            scanLocked = true;
            await recordCode(result.getText());
            timer = setTimeout(() => { scanLocked = false; }, 1800);
        });
        if (disposed || !scanning.value) session.stop(); else controls = session;
    } catch { stopCamera(); tell('Camera unavailable. Search by name or enter the badge reference instead.', 'warning'); }
}
function stopCamera() { controls?.stop(); controls = null; scanning.value = false; clearTimeout(timer); scanLocked = false; }
const connectionChanged = () => { online.value = navigator.onLine; if (online.value) void sync(); };
onMounted(async () => {
    try {
        let grant = offlineMovementAccess();
        if (online.value) {
            try {
                const { data } = await window.axios.get(endpoint, { timeout: 10000 }); grant = grantMovementAccess(data);
                key = movementCacheKey(grant); await update(value => { value.rosters = data.rosters; });
            } catch (error) {
                if (error.response || !grant) throw error;
                online.value = false;
                tell('Server unreachable. Using the roster saved on this phone.', 'warning');
            }
        }
        if (!online.value) {
            if (!grant) throw new Error('Connect and sign in to prepare this phone first.');
            key = movementCacheKey(grant); state.value = await movementStore(key);
        }
        rosterId.value = state.value.rosters[0]?.id || ''; await selectRoster();
        if (online.value) await sync();
        navigator.storage?.persist?.().catch(() => {});
    } catch (error) { tell(errorMessage(error), 'error'); }
    window.addEventListener('online', connectionChanged); window.addEventListener('offline', connectionChanged);
    refreshTimer = setInterval(() => { if (navigator.onLine) { online.value = true; void sync(); } }, 30000);
});
onBeforeUnmount(() => { disposed = true; stopCamera(); clearInterval(refreshTimer); window.removeEventListener('online', connectionChanged); window.removeEventListener('offline', connectionChanged); });
</script>

<style scoped>
.movement-page{padding-bottom:40px}.movement-page .event-portal-hero h1{max-width:850px;font-size:clamp(2.2rem,5vw,4rem)}.movement-actions{display:flex;flex-wrap:wrap;align-items:center;gap:12px}.movement-pill{padding:8px 12px;border-radius:20px;background:#ffffff20}.movement-caption{font-size:.85rem;opacity:.8;line-height:1.6}.movement-topbar{display:flex;align-items:end;flex-wrap:wrap;gap:24px;margin-bottom:24px}.movement-inline{display:flex;flex-wrap:wrap;align-items:end;gap:12px}.movement-page label{display:grid;gap:7px;font-weight:600}.movement-page input,.movement-page select{width:100%;padding:12px;border:1px solid #b8c5cf;border-radius:10px;color:#142433;background:white;font:inherit}.movement-form{display:grid;gap:16px}.movement-form h2{margin:0}.movement-setup,.movement-history,.movement-conflicts{padding:24px;border:1px solid var(--outline);border-radius:18px;margin:24px 0}.movement-setup summary{cursor:pointer;font-weight:700}.movement-setup-grid{display:grid;grid-template-columns:1fr 1fr;gap:40px;padding-top:24px}.movement-action-picker{display:flex;gap:12px}.movement-conflicts{background:#fff2cd;color:#493700}.movement-conflicts article{display:flex;justify-content:space-between;align-items:center;gap:16px;border-top:1px solid #b0a47c;padding:16px 0}.movement-conflicts p{margin:8px 0}.movement-history li{padding:10px 0;line-height:1.6}.movement-badge-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px;margin-top:20px}.movement-badge{display:grid;justify-items:center;text-align:center;border:1px solid #aaa;background:white;color:#142433;padding:20px;break-inside:avoid}.movement-badge h2{font-size:1.15rem}.movement-badge img{width:180px;height:180px}.movement-badge small{font-size:10px;overflow-wrap:anywhere}.movement-badges{margin-top:30px}@media(max-width:750px){.movement-setup-grid{grid-template-columns:1fr}.movement-topbar>*{width:100%}.movement-conflicts article{align-items:start;flex-direction:column}}@media print{.movement-page .event-portal-hero,.movement-page>.section>.container>:not(.movement-badges),.movement-no-print{display:none!important}.movement-badge-grid{grid-template-columns:repeat(3,1fr);gap:8mm}.movement-badges{margin:0}.movement-page>.section{padding:0}.movement-badge{padding:5mm}.movement-badge img{width:40mm;height:40mm}}
</style>
