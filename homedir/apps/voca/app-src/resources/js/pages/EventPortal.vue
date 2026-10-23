<template>
    <main class="event-portal-page">
        <section class="event-portal-hero"><div class="container"><p class="eyebrow">Leader access</p><h1>Event check-in</h1><p>Scan guest tickets as they arrive or find a registration manually.</p></div></section>
        <section class="section"><div class="container event-portal-layout">
            <div class="scanner-panel">
                <div class="scanner-heading"><div><p class="eyebrow">QR scanner</p><h2>Scan a ticket</h2></div><button v-if="scanning" class="button ghost" type="button" @click="stopScanner">Stop camera</button></div>
                <video v-show="scanning" ref="videoElement" class="scanner-viewport"></video>
                <button v-if="!scanning" class="button primary scanner-start" type="button" @click="startScanner">Open camera</button>
                <form class="manual-checkin" @submit.prevent="checkIn(manualCode)">
                    <label>Or enter ticket reference</label>
                    <div><input v-model.trim="manualCode" placeholder="PH-XXXXXXXXXXXX" /><button class="button primary" type="submit" :disabled="checking">Check in</button></div>
                </form>
                <div v-if="scanMessage" class="scan-result" :class="scanStatus" role="status"><strong>{{ scanMessage }}</strong><span v-if="lastGuest">{{ lastGuest.event_title }} · {{ lastGuest.party_size }} {{ lastGuest.party_size === 1 ? 'guest' : 'guests' }}</span></div>
            </div>
            <div class="event-attendees">
                <div class="portal-stats"><div><strong>{{ summary.registrations }}</strong><span>Bookings</span></div><div><strong>{{ summary.guests }}</strong><span>Expected</span></div><div><strong>{{ summary.checked_in }}</strong><span>Arrived</span></div></div>
                <div class="attendee-toolbar"><div><p class="eyebrow">Guest list</p><h2>Registrations</h2></div><input v-model="search" type="search" placeholder="Search name, email or reference" /></div>
                <p v-if="loadError" class="builder-error">{{ loadError }}</p>
                <div class="attendee-list">
                    <article v-for="item in registrations" :key="item.reference" class="attendee-row">
                        <div><strong>{{ item.name }}</strong><span>{{ item.email }} · {{ item.reference }}</span><small>{{ item.event_title }} · {{ item.party_size }} attending</small></div>
                        <span v-if="item.checked_in_at" class="attendee-status checked">Checked in</span>
                        <button v-else class="button ghost" type="button" @click="checkIn(item.reference)">Check in</button>
                    </article>
                    <p v-if="!loading && !registrations.length" class="schedules-empty">No registrations found.</p>
                </div>
            </div>
        </div></section>
    </main>
</template>

<script setup>
import { BrowserQRCodeReader } from '@zxing/browser';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

const registrations = ref([]);
const summary = ref({ registrations: 0, guests: 0, checked_in: 0 });
const loading = ref(false);
const loadError = ref('');
const search = ref('');
const manualCode = ref('');
const scanning = ref(false);
const checking = ref(false);
const scanMessage = ref('');
const scanStatus = ref('');
const lastGuest = ref(null);
const videoElement = ref(null);
let controls;
let searchTimer;
let scanLocked = false;

const loadRegistrations = async () => {
    loading.value = true;
    try {
        const { data } = await window.axios.get('/event-portal/registrations', { params: { search: search.value || undefined } });
        registrations.value = data.registrations;
        summary.value = data.summary;
        loadError.value = '';
    } catch (error) {
        loadError.value = error.response?.data?.message || 'The guest list could not be loaded.';
    } finally { loading.value = false; }
};

const checkIn = async (code) => {
    if (!code || checking.value) return;
    checking.value = true;
    try {
        const { data } = await window.axios.post('/event-portal/check-in', { code });
        scanMessage.value = data.message;
        scanStatus.value = 'success';
        lastGuest.value = data.registration;
        manualCode.value = '';
        navigator.vibrate?.(120);
        await loadRegistrations();
    } catch (error) {
        scanMessage.value = error.response?.data?.message || 'Ticket could not be checked in.';
        scanStatus.value = error.response?.status === 409 ? 'warning' : 'error';
        lastGuest.value = error.response?.data?.registration || null;
        navigator.vibrate?.([80, 50, 80]);
    } finally {
        checking.value = false;
        window.setTimeout(() => { scanLocked = false; }, 1500);
    }
};

const startScanner = async () => {
    scanMessage.value = '';
    scanning.value = true;
    await new Promise((resolve) => requestAnimationFrame(resolve));
    try {
        const reader = new BrowserQRCodeReader();
        controls = await reader.decodeFromConstraints(
            { video: { facingMode: { ideal: 'environment' } }, audio: false },
            videoElement.value,
            (result) => {
                if (result && !scanLocked) {
                    scanLocked = true;
                    checkIn(result.getText());
                }
            },
        );
    } catch {
        scanning.value = false;
        scanMessage.value = 'Camera access was unavailable. Enter the reference printed below the QR code instead.';
        scanStatus.value = 'error';
    }
};
const stopScanner = () => { controls?.stop(); controls = null; scanning.value = false; };
watch(search, () => { window.clearTimeout(searchTimer); searchTimer = window.setTimeout(loadRegistrations, 300); });
onMounted(loadRegistrations);
onBeforeUnmount(stopScanner);
</script>
