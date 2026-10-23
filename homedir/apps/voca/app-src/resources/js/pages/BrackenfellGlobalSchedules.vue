<template>
    <section class="section portal-shell">
        <div class="container portal-detail">
            <div class="portal-detail-hero admin" :style="portalStyle">
                <div>
                    <p class="portal-tag">Superadmin</p>
                    <h1>Event scheduling</h1>
                    <p>Create, edit, and review events across all ministries.</p>
                </div>
                <div class="portal-user-card">
                    <p class="portal-user-label">Signed in as</p>
                    <p class="portal-user-name">{{ user?.name || 'Superadmin' }}</p>
                    <p class="portal-user-role">Superadmin</p>
                    <div class="portal-user-actions">
                        <RouterLink class="button ghost" to="/brackenfell">Back to portals</RouterLink>
                        <button class="button" type="button" @click="logoutUser">Sign out</button>
                    </div>
                </div>
            </div>

            <div class="schedules-card scheduling-ministry-picker">
                <div>
                    <p class="portal-tag">Manage events</p>
                    <h2>Choose a ministry</h2>
                    <p>Select the ministry responsible for the event, then create or edit its schedule below.</p>
                </div>
                <label>
                    Ministry
                    <select v-model="selectedMinistrySlug" @change="selectedSchedule = null">
                        <option value="">Choose a ministry</option>
                        <option v-for="item in ministries" :key="item.slug" :value="item.slug">
                            {{ item.name }}
                        </option>
                    </select>
                </label>
            </div>

            <SchedulesManager
                v-if="selectedMinistrySlug"
                :key="selectedMinistrySlug"
                entity="brackenfell"
                :ministry-slug="selectedMinistrySlug"
                :schedule-to-edit="selectedSchedule"
                @saved="handleScheduleChanged"
                @deleted="handleScheduleChanged"
            />

            <div class="schedules-header global">
                <div>
                    <h2>Schedules</h2>
                    <p>Plan upcoming services and share assignments with the team.</p>
                </div>
                <div class="schedules-actions">
                    <button class="button ghost" type="button" @click="viewMode = 'list'">
                        List view
                    </button>
                    <button class="button ghost" type="button" @click="viewMode = 'calendar'">
                        Calendar
                    </button>
                </div>
            </div>

            <div class="schedules-card">
                <div class="schedules-card-header">
                    <h3>Upcoming schedule</h3>
                    <div class="schedules-header-actions">
                        <label>
                            Range start
                            <input v-model="rangeStart" type="date" />
                        </label>
                        <label>
                            Range end
                            <input v-model="rangeEnd" type="date" />
                        </label>
                        <button class="button ghost" type="button" @click="loadOccurrences">
                            Refresh
                        </button>
                    </div>
                </div>

                <div v-if="loading" class="schedules-loading">Loading schedules...</div>
                <div v-else-if="!occurrences.length" class="schedules-empty">No schedules found.</div>
                <div v-else class="schedules-list">
                    <article v-for="item in occurrences" :key="item.occurrence_id" class="schedules-item">
                        <img v-if="item.thumbnail_url" class="schedules-item-thumbnail" :src="item.thumbnail_url" :alt="item.title" loading="lazy" />
                        <div>
                            <p class="schedules-title">{{ item.title }}</p>
                            <p class="schedules-meta">
                                {{ formatDate(item.date) }} · {{ item.start_time }}
                                <span v-if="item.end_time">- {{ item.end_time }}</span>
                            </p>
                            <p class="schedules-meta">{{ item.ministry?.name }}</p>
                            <p v-if="item.location" class="schedules-meta">{{ item.location }}</p>
                        </div>
                        <div class="schedules-item-actions">
                            <button class="button ghost" type="button" @click="manageMinistry(item)">
                                Manage & edit
                            </button>
                        </div>
                    </article>
                </div>
            </div>

            <div v-if="viewMode === 'calendar'" class="schedules-calendar">
                <div class="schedules-calendar-header">
                    <button class="button ghost" type="button" @click="previousMonth">Previous</button>
                    <h3>{{ monthLabel }}</h3>
                    <button class="button ghost" type="button" @click="nextMonth">Next</button>
                </div>
                <div class="schedules-calendar-grid">
                    <div class="schedules-calendar-day" v-for="label in weekdayLabels" :key="label">
                        {{ label }}
                    </div>
                    <button
                        v-for="day in calendarDays"
                        :key="day.key"
                        class="schedules-calendar-cell"
                        :class="{ muted: !day.inMonth, active: day.date === selectedDate }"
                        type="button"
                        @click="selectDate(day.date)"
                    >
                        <span>{{ day.label }}</span>
                        <span v-if="day.count" class="schedules-calendar-count">{{ day.count }}</span>
                    </button>
                </div>
                <div class="schedules-calendar-list">
                    <h4>Schedules on {{ formatDate(selectedDate) }}</h4>
                    <div v-if="!selectedDayItems.length" class="schedules-empty">Nothing scheduled.</div>
                    <div v-else class="schedules-list">
                        <article v-for="item in selectedDayItems" :key="item.occurrence_id" class="schedules-item">
                            <img v-if="item.thumbnail_url" class="schedules-item-thumbnail" :src="item.thumbnail_url" :alt="item.title" loading="lazy" />
                            <div>
                                <p class="schedules-title">{{ item.title }}</p>
                                <p class="schedules-meta">
                                    {{ item.start_time }}
                                    <span v-if="item.end_time">- {{ item.end_time }}</span>
                                </p>
                                <p class="schedules-meta">{{ item.ministry?.name }}</p>
                            </div>
                            <div class="schedules-item-actions">
                                <button class="button ghost" type="button" @click="manageMinistry(item)">
                                    Manage & edit
                                </button>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRouter, RouterLink } from 'vue-router';
import { useAuth } from '../composables/useAuth';
import { useMinistries } from '../composables/useMinistries';
import SchedulesManager from '../components/SchedulesManager.vue';

const entity = 'brackenfell';
const router = useRouter();
const { user, logout, load } = useAuth();
const { ministriesByEntity, loadEntity, getMinistry } = useMinistries();

const ministry = computed(() => getMinistry(entity, selectedMinistrySlug.value));
const portalStyle = computed(() => ({
    '--portal-accent': ministry.value?.theme?.accent || '#1d5fa2',
    '--portal-surface': ministry.value?.theme?.surface || '#ffffff',
    '--portal-gradient': ministry.value?.theme?.gradient || 'linear-gradient(135deg, #1d5fa2, #0b2b4b)',
}));

const viewMode = ref('list');
const loading = ref(false);
const occurrences = ref([]);
const selectedMinistrySlug = ref('');
const selectedSchedule = ref(null);
const ministries = computed(() => ministriesByEntity.value[entity] || []);

const today = new Date();
const rangeStart = ref(today.toISOString().slice(0, 10));
const rangeEnd = ref(new Date(today.getTime() + 30 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10));

const weekdayLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const calendarMonth = ref(new Date());
const selectedDate = ref(rangeStart.value);

const monthLabel = computed(() =>
    calendarMonth.value.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
);

const calendarDays = computed(() => {
    const year = calendarMonth.value.getFullYear();
    const month = calendarMonth.value.getMonth();
    const firstDay = new Date(year, month, 1);
    const startWeekday = (firstDay.getDay() + 6) % 7;
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const totalCells = Math.ceil((startWeekday + daysInMonth) / 7) * 7;
    const days = [];
    for (let i = 0; i < totalCells; i++) {
        const dayNumber = i - startWeekday + 1;
        const dateObj = new Date(year, month, dayNumber);
        const dateString = dateObj.toISOString().slice(0, 10);
        const inMonth = dayNumber >= 1 && dayNumber <= daysInMonth;
        const count = occurrences.value.filter((item) => item.date === dateString).length;
        days.push({
            key: `${year}-${month}-${i}`,
            label: dateObj.getDate(),
            date: dateString,
            inMonth,
            count,
        });
    }
    return days;
});

const selectedDayItems = computed(() =>
    occurrences.value.filter((item) => item.date === selectedDate.value)
);

const formatDate = (value) =>
    new Date(value).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });

const loadOccurrences = async () => {
    loading.value = true;
    const { data } = await window.axios.get('/brackenfell/schedules', {
        params: { start: rangeStart.value, end: rangeEnd.value },
    });
    occurrences.value = data.occurrences || [];
    loading.value = false;
};

const previousMonth = () => {
    const current = calendarMonth.value;
    calendarMonth.value = new Date(current.getFullYear(), current.getMonth() - 1, 1);
};

const nextMonth = () => {
    const current = calendarMonth.value;
    calendarMonth.value = new Date(current.getFullYear(), current.getMonth() + 1, 1);
};

const selectDate = (date) => {
    selectedDate.value = date;
};

const manageMinistry = (item) => {
    selectedMinistrySlug.value = item.ministry?.slug || '';
    selectedSchedule.value = item;
    window.requestAnimationFrame(() => {
        document.querySelector('.scheduling-ministry-picker')?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        });
    });
};

const handleScheduleChanged = async () => {
    selectedSchedule.value = null;
    await loadOccurrences();
};

const syncRangeToMonth = () => {
    const year = calendarMonth.value.getFullYear();
    const month = calendarMonth.value.getMonth();
    const start = new Date(year, month, 1);
    const end = new Date(year, month + 1, 0);
    rangeStart.value = start.toISOString().slice(0, 10);
    rangeEnd.value = end.toISOString().slice(0, 10);
    selectedDate.value = rangeStart.value;
    loadOccurrences();
};

watch(calendarMonth, () => {
    if (viewMode.value === 'calendar') {
        syncRangeToMonth();
    }
});

watch(viewMode, (mode) => {
    if (mode === 'calendar') {
        syncRangeToMonth();
    }
});

const logoutUser = async () => {
    await logout();
    router.push('/login');
};

onMounted(async () => {
    if (!ministriesByEntity.value[entity]) {
        await loadEntity(entity);
    }
    await load();
    await loadOccurrences();
});
</script>
