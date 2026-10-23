<template>
    <main class="ticket-page">
        <section v-if="loading" class="ticket-card">Loading your ticket…</section>
        <section v-else-if="error" class="ticket-card">
            <p class="eyebrow">Ticket unavailable</p>
            <h1>{{ error }}</h1>
            <RouterLink class="button primary" to="/calendar">Back to events</RouterLink>
        </section>
        <section v-else class="ticket-card">
            <div class="ticket-success">✓ Registration confirmed</div>
            <p class="eyebrow">VOCA event ticket</p>
            <h1>{{ registration.event_title }}</h1>
            <div class="ticket-details">
                <p><strong>{{ registration.name }}</strong> · {{ registration.party_size }} {{ registration.party_size === 1 ? 'guest' : 'guests' }}</p>
                <p>{{ formatDate(registration.event_date) }} · {{ registration.event_start_time }}</p>
                <p v-if="registration.event_location">{{ registration.event_location }}</p>
            </div>
            <div class="ticket-qr-wrap">
                <img v-if="qrDataUrl" :src="qrDataUrl" alt="Event check-in QR code" class="ticket-qr" />
            </div>
            <p class="ticket-reference">{{ registration.reference }}</p>
            <p class="ticket-instruction">Keep this page or take a screenshot. A leader will scan the QR code when you arrive.</p>
            <div v-if="registration.checked_in_at" class="ticket-checked-in">Checked in ✓</div>
            <button class="button ghost ticket-print" type="button" @click="printTicket">Print ticket</button>
        </section>
    </main>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import QRCode from 'qrcode';

const route = useRoute();
const loading = ref(true);
const error = ref('');
const registration = ref(null);
const qrDataUrl = ref('');
const printTicket = () => window.print();
const formatDate = (value) => new Date(`${value}T12:00:00`).toLocaleDateString('en-ZA', {
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
});

onMounted(async () => {
    try {
        const { data } = await window.axios.get(`/events/tickets/${encodeURIComponent(route.params.token)}`);
        registration.value = data.registration;
        qrDataUrl.value = await QRCode.toDataURL(`PH-TICKET:${route.params.token}`, {
            width: 360, margin: 2, color: { dark: '#07152b', light: '#ffffff' },
        });
    } catch {
        error.value = 'This ticket link is invalid or has expired.';
    } finally {
        loading.value = false;
    }
});
</script>
