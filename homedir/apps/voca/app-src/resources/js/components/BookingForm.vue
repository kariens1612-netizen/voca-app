<template>
    <section id="booking" class="booking">
        <div class="container booking-frame">
            <div class="section-header centered">
                <p class="eyebrow">Appointments</p>
                <h2>Book a session with our team</h2>
                <p class="section-subtitle">
                    Choose a ministry, pick a date, and send through your booking request.
                </p>
            </div>

            <div class="booking-grid">
                <form class="card form" @submit.prevent="submitBooking">
                    <div class="form-row">
                        <label>Full name</label>
                        <input v-model.trim="form.name" type="text" placeholder="Your name" required />
                    </div>
                    <div class="form-row">
                        <label>Email</label>
                        <input v-model.trim="form.email" type="email" placeholder="you@email.com" required />
                    </div>
                    <div class="form-row">
                        <label>Phone</label>
                        <input v-model.trim="form.phone" type="tel" placeholder="+27" required />
                    </div>
                    <div class="form-row">
                        <label>Ministry</label>
                        <select v-model="form.ministry" required>
                            <option value="" disabled>Select a ministry</option>
                            <option v-for="ministry in ministryOptions" :key="ministry.slug" :value="ministry.slug">
                                {{ ministry.title }}
                            </option>
                        </select>
                    </div>
                    <div class="form-row split">
                        <div>
                            <label>Date</label>
                            <input v-model="form.date" type="date" required />
                        </div>
                        <div>
                            <label>Time</label>
                            <input v-model="form.time" type="time" required />
                        </div>
                    </div>
                    <div class="form-row">
                        <label>Notes</label>
                        <textarea v-model.trim="form.notes" rows="3" placeholder="Anything we should know?"></textarea>
                    </div>
                    <div class="form-row">
                        <label>Appointment fee (ZAR)</label>
                        <input v-model.number="form.amount" type="number" min="0" step="10" required />
                    </div>

                    <div class="form-actions">
                        <button class="button" type="submit" :disabled="loading">
                            {{ loading ? 'Creating booking...' : 'Create booking' }}
                        </button>
                        <p v-if="error" class="error">{{ error }}</p>
                        <p v-if="success" class="success">{{ success }}</p>
                    </div>
                </form>

                <div v-if="booking && Number(booking.amount) > 0" class="card payment">
                    <p class="eyebrow">Secure payment</p>
                    <h3>Pay R{{ Number(booking.amount).toFixed(2) }}</h3>
                    <p>Choose your preferred secure payment provider.</p>
                    <div class="form-actions">
                        <button class="button" type="button" :disabled="paying" @click="startPayment('ozow')">
                            Pay with Ozow
                        </button>
                        <button class="button button-secondary" type="button" :disabled="paying" @click="startPayment('payfast')">
                            Pay with PayFast
                        </button>
                        <p v-if="paymentError" class="error">{{ paymentError }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { ref, watch } from 'vue';
import { ministries } from '../data/ministries';

const props = defineProps({
    ministrySlug: {
        type: String,
        default: '',
    },
});

const ministryOptions = ministries.map((ministry) => ({
    slug: ministry.slug,
    title: ministry.title,
}));

const form = ref({
    name: '',
    email: '',
    phone: '',
    ministry: '',
    date: '',
    time: '',
    notes: '',
    amount: 0,
});

const loading = ref(false);
const error = ref('');
const success = ref('');
const booking = ref(null);
const paying = ref(false);
const paymentError = ref('');

const setMinistryFromProp = () => {
    if (!props.ministrySlug) {
        return;
    }
    const exists = ministryOptions.some((ministry) => ministry.slug === props.ministrySlug);
    if (exists) {
        form.value.ministry = props.ministrySlug;
    }
};

const startPayment = async (provider) => {
    paying.value = true;
    paymentError.value = '';

    try {
        const response = await fetch(window.apiUrl(`/api/bookings/${booking.value.id}/pay`), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ provider }),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(payload.message || 'Unable to start payment.');
        }

        const checkout = payload.data;
        const paymentForm = document.createElement('form');
        paymentForm.method = checkout.method || 'POST';
        paymentForm.action = checkout.url;

        Object.entries(checkout.fields || {}).forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = typeof value === 'boolean' ? String(value).toLowerCase() : value;
            paymentForm.appendChild(input);
        });

        document.body.appendChild(paymentForm);
        paymentForm.submit();
    } catch (err) {
        paymentError.value = err.message;
        paying.value = false;
    }
};

watch(
    () => props.ministrySlug,
    () => setMinistryFromProp(),
    { immediate: true }
);

const submitBooking = async () => {
    loading.value = true;
    error.value = '';
    success.value = '';

    try {
        const response = await fetch(window.apiUrl('/api/bookings'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: form.value.name,
                email: form.value.email,
                phone: form.value.phone,
                ministry: form.value.ministry,
                appointment_date: form.value.date,
                appointment_time: form.value.time,
                notes: form.value.notes,
                amount: form.value.amount,
            }),
        });

        if (!response.ok) {
            const payload = await response.json().catch(() => ({}));
            throw new Error(payload.message || 'Unable to create booking.');
        }

        const payload = await response.json();
        booking.value = payload.data;
        success.value = 'Booking created. Our team will follow up shortly.';
    } catch (err) {
        error.value = err.message;
    } finally {
        loading.value = false;
    }
};
</script>
