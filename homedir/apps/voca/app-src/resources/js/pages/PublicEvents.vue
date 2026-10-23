<template>
    <main class="public-events">
        <section class="public-events-hero">
            <div class="container">
                <p class="eyebrow">Voca Ministries</p>
                <h1>Events calendar</h1>
                <p>Services, gatherings, and ministry events from across the Voca family.</p>
            </div>
        </section>

        <section class="section">
            <div class="container">
                <div class="public-events-controls">
                    <label>
                        Ministry
                        <select v-model="ministryFilter">
                            <option value="">All ministries</option>
                            <option v-for="ministry in ministries" :key="ministry.slug" :value="ministry.slug">
                                {{ ministry.name }}
                            </option>
                        </select>
                    </label>
                    <div class="schedules-actions">
                        <button class="button ghost" type="button" @click="previousMonth">Previous</button>
                        <strong>{{ monthLabel }}</strong>
                        <button class="button ghost" type="button" @click="nextMonth">Next</button>
                    </div>
                </div>

                <p v-if="error" class="builder-error" role="alert">{{ error }}</p>
                <div v-if="loading" class="schedules-loading">Loading events...</div>

                <div v-else class="public-calendar">
                    <div class="schedules-calendar-grid">
                        <div v-for="label in weekdayLabels" :key="label" class="schedules-calendar-day">
                            {{ label }}
                        </div>
                        <button
                            v-for="day in calendarDays"
                            :key="day.date"
                            class="schedules-calendar-cell"
                            :class="{ muted: !day.inMonth, active: day.date === selectedDate }"
                            type="button"
                            @click="selectedDate = day.date"
                        >
                            <span class="schedules-calendar-date">{{ day.label }}</span>
                            <span v-if="day.events.length" class="public-calendar-events">
                                <span
                                    v-for="event in day.events.slice(0, 2)"
                                    :key="event.occurrence_id"
                                    class="public-calendar-event"
                                >
                                    <strong>{{ event.title }}</strong>
                                    <small>{{ formatTime(event) }}</small>
                                </span>
                                <span v-if="day.events.length > 2" class="public-calendar-more">
                                    +{{ day.events.length - 2 }} more
                                </span>
                            </span>
                        </button>
                    </div>

                    <aside class="public-events-day">
                        <p class="eyebrow">Selected date</p>
                        <h2>{{ formatFullDate(selectedDate) }}</h2>
                        <div v-if="!selectedEvents.length" class="schedules-empty">No public events on this date.</div>
                        <div v-else class="public-event-list">
                            <article v-for="event in selectedEvents" :key="event.occurrence_id" class="public-event-card">
                                <img v-if="event.thumbnail_url" class="public-event-thumbnail" :src="event.thumbnail_url" :alt="event.title" loading="lazy" />
                                <p class="portal-tag">{{ event.ministry.name }}</p>
                                <h3>{{ event.title }}</h3>
                                <p>{{ formatTime(event) }}</p>
                                <p v-if="event.location">{{ event.location }}</p>
                                <button class="button primary" type="button" @click="openRegistration(event)">Register</button>
                            </article>
                        </div>
                    </aside>
                </div>

                <div class="public-upcoming">
                    <div class="section-header">
                        <p class="eyebrow">This month</p>
                        <h2>Upcoming events</h2>
                    </div>
                    <div v-if="!filteredEvents.length" class="schedules-empty">No events scheduled for this month.</div>
                    <div v-else class="public-event-grid">
                        <article v-for="event in filteredEvents" :key="event.occurrence_id" class="public-event-card">
                            <img v-if="event.thumbnail_url" class="public-event-thumbnail" :src="event.thumbnail_url" :alt="event.title" loading="lazy" />
                            <p class="portal-tag">{{ event.ministry.name }}</p>
                            <h3>{{ event.title }}</h3>
                            <p>{{ formatFullDate(event.date) }} · {{ formatTime(event) }}</p>
                            <p v-if="event.location">{{ event.location }}</p>
                            <button class="button primary" type="button" @click="openRegistration(event)">Register</button>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <div v-if="registeringFor" class="event-modal-backdrop" @click.self="closeRegistration">
            <section class="event-modal" role="dialog" aria-modal="true" aria-labelledby="register-title">
                <button class="event-modal-close" type="button" aria-label="Close" @click="closeRegistration">×</button>
                <p class="eyebrow">Event registration</p>
                <h2 id="register-title">{{ registeringFor.title }}</h2>
                <p>{{ formatFullDate(registeringFor.date) }} · {{ formatTime(registeringFor) }}</p>
                <p v-if="registeringFor.location">{{ registeringFor.location }}</p>
                <form class="event-registration-form" @submit.prevent="submitRegistration">
                    <label>Full name<input v-model.trim="form.name" required maxlength="120" autocomplete="name" /></label>
                    <label>Email address<input v-model.trim="form.email" required type="email" maxlength="190" autocomplete="email" /></label>
                    <label>Mobile number <span>(optional)</span><input v-model.trim="form.phone" type="tel" maxlength="30" autocomplete="tel" /></label>
                    <label>Number attending<input v-model.number="form.party_size" required type="number" min="1" max="20" /></label>
                    <p v-if="registrationError" class="builder-error" role="alert">{{ registrationError }}</p>
                    <button class="button primary" type="submit" :disabled="submitting">{{ submitting ? 'Creating ticket…' : 'Complete registration' }}</button>
                </form>
            </section>
        </div>
    </main>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();

const toDateValue = (date) => [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, '0'),
    String(date.getDate()).padStart(2, '0'),
].join('-');

const loading = ref(false);
const error = ref('');
const events = ref([]);
const ministryFilter = ref('');
const calendarMonth = ref(new Date());
const selectedDate = ref(toDateValue(new Date()));
const weekdayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const registeringFor = ref(null);
const submitting = ref(false);
const registrationError = ref('');
const form = ref({ name: '', email: '', phone: '', party_size: 1 });

const monthLabel = computed(() =>
    calendarMonth.value.toLocaleDateString('en-ZA', { month: 'long', year: 'numeric' })
);

const ministries = computed(() => {
    const unique = new Map();
    events.value.forEach((event) => unique.set(event.ministry.slug, event.ministry));
    return [...unique.values()].sort((a, b) => a.name.localeCompare(b.name));
});

const filteredEvents = computed(() =>
    events.value.filter((event) => !ministryFilter.value || event.ministry.slug === ministryFilter.value)
);

const selectedEvents = computed(() =>
    filteredEvents.value.filter((event) => event.date === selectedDate.value)
);

const calendarDays = computed(() => {
    const year = calendarMonth.value.getFullYear();
    const month = calendarMonth.value.getMonth();
    const first = new Date(year, month, 1);
    const offset = (first.getDay() + 6) % 7;
    const total = Math.ceil((offset + new Date(year, month + 1, 0).getDate()) / 7) * 7;

    return Array.from({ length: total }, (_, index) => {
        const date = new Date(year, month, index - offset + 1);
        const value = toDateValue(date);
        const dayEvents = filteredEvents.value.filter((event) => event.date === value);
        return {
            date: value,
            label: date.getDate(),
            inMonth: date.getMonth() === month,
            count: dayEvents.length,
            events: dayEvents,
        };
    });
});

const loadEvents = async () => {
    loading.value = true;
    error.value = '';
    const year = calendarMonth.value.getFullYear();
    const month = calendarMonth.value.getMonth();
    const start = toDateValue(new Date(year, month, 1));
    const end = toDateValue(new Date(year, month + 1, 0));

    try {
        const { data } = await window.axios.get('/events', { params: { start, end } });
        events.value = data.events || [];
        if (!selectedDate.value.startsWith(`${year}-${String(month + 1).padStart(2, '0')}`)) {
            selectedDate.value = start;
        }
    } catch {
        error.value = 'The events calendar could not be loaded.';
    } finally {
        loading.value = false;
    }
};

const previousMonth = () => {
    calendarMonth.value = new Date(calendarMonth.value.getFullYear(), calendarMonth.value.getMonth() - 1, 1);
};

const nextMonth = () => {
    calendarMonth.value = new Date(calendarMonth.value.getFullYear(), calendarMonth.value.getMonth() + 1, 1);
};

const formatFullDate = (value) =>
    new Date(`${value}T12:00:00`).toLocaleDateString('en-ZA', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

const formatTime = (event) =>
    event.end_time ? `${event.start_time} – ${event.end_time}` : event.start_time;

const openRegistration = (event) => {
    registeringFor.value = event;
    registrationError.value = '';
};
const closeRegistration = () => {
    if (!submitting.value) registeringFor.value = null;
};
const submitRegistration = async () => {
    submitting.value = true;
    registrationError.value = '';
    try {
        const { data } = await window.axios.post('/events/register', {
            occurrence_id: registeringFor.value.occurrence_id,
            ...form.value,
        });
        await router.push({ name: 'event-ticket', params: { token: data.ticket_token } });
    } catch (error) {
        registrationError.value = error.response?.data?.message || 'Registration could not be completed. Please try again.';
    } finally {
        submitting.value = false;
    }
};

watch(calendarMonth, loadEvents);
onMounted(loadEvents);
</script>
