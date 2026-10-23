<template>
    <section class="schedules">
        <div class="schedules-header">
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

        <p v-if="notice" class="builder-notice" role="status">{{ notice }}</p>
        <p v-if="error" class="builder-error" role="alert">{{ error }}</p>

        <div class="schedules-grid">
            <div class="schedules-card">
                <h3>{{ editingScheduleId ? 'Edit schedule' : 'Create schedule' }}</h3>
                <form class="schedules-form" @submit.prevent="saveSchedule">
                    <label>
                        Title
                        <input v-model="form.title" type="text" maxlength="200" required />
                    </label>
                    <label>
                        Date
                        <input v-model="form.date" type="date" required />
                    </label>
                    <div class="schedules-form-row">
                        <label>
                            Start time
                            <input v-model="form.start_time" type="time" required />
                        </label>
                        <label>
                            End time
                            <input v-model="form.end_time" type="time" />
                        </label>
                    </div>
                    <label>
                        Location
                        <input v-model="form.location" type="text" maxlength="200" />
                    </label>
                    <label>
                        Thumbnail image URL
                        <input v-model="form.thumbnail_url" type="url" maxlength="2048" placeholder="https://..." />
                    </label>
                    <div class="schedule-media-tools">
                        <div class="schedule-media-actions">
                            <label class="schedule-upload-button">
                                <input type="file" accept="image/*" @change="uploadThumbnail" />
                                Upload image
                            </label>
                            <button class="button ghost" type="button" @click="toggleMediaLibrary">
                                {{ showMediaLibrary ? 'Hide library' : 'Choose from media' }}
                            </button>
                        </div>
                        <p v-if="mediaError" class="builder-error" role="alert">{{ mediaError }}</p>
                        <div v-if="form.thumbnail_url" class="schedule-thumbnail-preview">
                            <img :src="form.thumbnail_url" alt="" loading="lazy" />
                            <button class="button outline" type="button" @click="form.thumbnail_url = ''">
                                Remove image
                            </button>
                        </div>
                        <div v-if="showMediaLibrary" class="schedule-media-library">
                            <div v-if="mediaLoading" class="schedules-loading">Loading media...</div>
                            <button
                                v-for="item in mediaImages"
                                v-else
                                :key="item.id"
                                class="schedule-media-option"
                                type="button"
                                @click="useMedia(item)"
                            >
                                <img :src="item.url" :alt="item.name" loading="lazy" />
                                <span>{{ item.name }}</span>
                            </button>
                            <p v-if="!mediaLoading && !mediaImages.length" class="schedules-empty">No shared images yet.</p>
                        </div>
                    </div>
                    <label>
                        Notes
                        <textarea v-model="form.notes" rows="3" maxlength="4000"></textarea>
                    </label>
                    <label v-if="canAssign">
                        Assign team members
                        <select v-model="form.assignees" multiple>
                            <option v-for="member in members" :key="member.id" :value="member.id">
                                {{ member.name }} ({{ member.role }})
                            </option>
                        </select>
                    </label>
                    <label class="schedules-toggle">
                        <input v-model="form.is_recurring" type="checkbox" />
                        Recurring schedule
                    </label>
                    <div v-if="form.is_recurring" class="schedules-recurring">
                        <label>
                            Frequency
                            <select v-model="form.recurrence_frequency">
                                <option value="weekly">Weekly</option>
                                <option value="daily">Daily</option>
                                <option value="monthly">Monthly</option>
                            </select>
                        </label>
                        <label>
                            Repeat every
                            <input v-model.number="form.recurrence_interval" type="number" min="1" max="365" />
                        </label>
                        <label v-if="form.recurrence_frequency === 'weekly'">
                            Days of week
                            <div class="schedules-weekdays">
                                <label v-for="day in weekdays" :key="day.value">
                                    <input
                                        type="checkbox"
                                        :value="day.value"
                                        v-model="form.recurrence_byweekday"
                                    />
                                    {{ day.label }}
                                </label>
                            </div>
                        </label>
                        <label>
                            Until (optional)
                            <input v-model="form.recurrence_until" type="date" />
                        </label>
                    </div>
                    <div class="schedules-form-actions">
                        <button class="button" type="submit" :disabled="saving">
                            {{ editingScheduleId ? 'Update schedule' : 'Save schedule' }}
                        </button>
                        <button
                            v-if="editingScheduleId"
                            class="button outline"
                            type="button"
                            @click="resetForm"
                        >
                            Cancel edit
                        </button>
                    </div>
                </form>
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
                            <p v-if="item.location" class="schedules-meta">{{ item.location }}</p>
                            <p v-if="item.assignees.length" class="schedules-meta">
                                Assigned:
                                {{ item.assignees.map((person) => person.name).join(', ') }}
                            </p>
                        </div>
                        <div class="schedules-item-actions">
                            <button class="button ghost" type="button" @click="editSchedule(item)">
                                Edit
                            </button>
                            <button class="button outline" type="button" @click="deleteSchedule(item)">
                                Delete
                            </button>
                        </div>
                    </article>
                </div>
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
                            <p v-if="item.location" class="schedules-meta">{{ item.location }}</p>
                        </div>
                        <div class="schedules-item-actions">
                            <button class="button ghost" type="button" @click="editSchedule(item)">
                                Edit
                            </button>
                            <button class="button outline" type="button" @click="deleteSchedule(item)">
                                Delete
                            </button>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';

const props = defineProps({
    entity: { type: String, required: true },
    ministrySlug: { type: String, required: true },
    canAssign: { type: Boolean, default: true },
    scheduleToEdit: { type: Object, default: null },
});
const emit = defineEmits(['saved', 'deleted']);

const viewMode = ref('list');
const members = ref([]);
const occurrences = ref([]);
const loading = ref(false);
const saving = ref(false);
const editingScheduleId = ref(null);
const notice = ref('');
const error = ref('');
const mediaImages = ref([]);
const mediaLoading = ref(false);
const mediaError = ref('');
const showMediaLibrary = ref(false);

const today = new Date();
const rangeStart = ref(today.toISOString().slice(0, 10));
const rangeEnd = ref(new Date(today.getTime() + 30 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10));

const form = ref({
    title: '',
    date: today.toISOString().slice(0, 10),
    start_time: '09:00',
    end_time: '',
    location: '',
    thumbnail_url: '',
    notes: '',
    assignees: [],
    is_recurring: false,
    recurrence_frequency: 'weekly',
    recurrence_interval: 1,
    recurrence_byweekday: [],
    recurrence_until: '',
});

const weekdays = [
    { value: 1, label: 'Mon' },
    { value: 2, label: 'Tue' },
    { value: 3, label: 'Wed' },
    { value: 4, label: 'Thu' },
    { value: 5, label: 'Fri' },
    { value: 6, label: 'Sat' },
    { value: 7, label: 'Sun' },
];

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

const loadMembers = async () => {
    if (!props.canAssign) {
        members.value = [];
        return;
    }
    const response = await window.axios.get(
        `/${props.entity}/ministries/${props.ministrySlug}/members`
    );
    members.value = response.data.members;
};

const loadOccurrences = async () => {
    loading.value = true;
    const { data } = await window.axios.get(
        `/${props.entity}/ministries/${props.ministrySlug}/schedules`,
        { params: { start: rangeStart.value, end: rangeEnd.value } }
    );
    occurrences.value = data.occurrences || [];
    loading.value = false;
};

const loadMediaImages = async () => {
    mediaLoading.value = true;
    mediaError.value = '';
    try {
        const response = await window.axios.get('/api/media', { params: { type: 'image' } });
        mediaImages.value = response.data.data.data || [];
    } catch (requestError) {
        mediaError.value = errorMessage(requestError, 'The shared media library could not be loaded.');
    } finally {
        mediaLoading.value = false;
    }
};

const toggleMediaLibrary = async () => {
    showMediaLibrary.value = !showMediaLibrary.value;
    if (showMediaLibrary.value && !mediaImages.value.length) {
        await loadMediaImages();
    }
};

const useMedia = (item) => {
    form.value.thumbnail_url = item.url;
    showMediaLibrary.value = false;
};

const uploadThumbnail = async (event) => {
    const file = event.target.files?.[0];
    mediaError.value = '';
    if (!file) return;
    if (file.size > 8 * 1024 * 1024) {
        mediaError.value = 'Choose an image smaller than 8 MB.';
        event.target.value = '';
        return;
    }

    const body = new FormData();
    body.append('asset', file);
    try {
        const response = await window.axios.post('/api/media', body, { headers: { 'Content-Type': 'multipart/form-data' } });
        form.value.thumbnail_url = response.data.data.url;
        await loadMediaImages();
        notice.value = 'Image uploaded and added to the schedule.';
    } catch (requestError) {
        mediaError.value = errorMessage(requestError, 'The image could not be uploaded.');
    } finally {
        event.target.value = '';
    }
};

const resetForm = () => {
    editingScheduleId.value = null;
    form.value = {
        title: '',
        date: rangeStart.value,
        start_time: '09:00',
        end_time: '',
        location: '',
        thumbnail_url: '',
        notes: '',
        assignees: [],
        is_recurring: false,
        recurrence_frequency: 'weekly',
        recurrence_interval: 1,
        recurrence_byweekday: [],
        recurrence_until: '',
    };
};

const normalizeTime = (value) => value ? String(value).slice(0, 5) : '';

const errorMessage = (requestError, fallback) => {
    const validationErrors = requestError?.response?.data?.errors;
    if (validationErrors) {
        const first = Object.values(validationErrors).flat()[0];
        if (first) {
            return first;
        }
    }
    return requestError?.response?.data?.message || fallback;
};

const includeFormDateInRange = () => {
    if (form.value.date < rangeStart.value) {
        rangeStart.value = form.value.date;
    }
    if (form.value.date > rangeEnd.value) {
        rangeEnd.value = form.value.date;
    }
};

const saveSchedule = async () => {
    notice.value = '';
    error.value = '';
    saving.value = true;
    const wasEditing = Boolean(editingScheduleId.value);
    try {
        const payload = {
            ...form.value,
            start_time: normalizeTime(form.value.start_time),
            end_time: normalizeTime(form.value.end_time),
            assignees: form.value.assignees.map((id) => Number(id)),
        };
        if (!props.canAssign) {
            payload.assignees = [];
        }
        if (!payload.is_recurring) {
            payload.recurrence_frequency = null;
            payload.recurrence_interval = null;
            payload.recurrence_byweekday = [];
            payload.recurrence_until = null;
        }
        if (wasEditing) {
            await window.axios.patch(
                `/${props.entity}/ministries/${props.ministrySlug}/schedules/${editingScheduleId.value}`,
                payload
            );
        } else {
            await window.axios.post(
                `/${props.entity}/ministries/${props.ministrySlug}/schedules`,
                payload
            );
        }
        includeFormDateInRange();
        await loadOccurrences();
        resetForm();
        notice.value = wasEditing ? 'Schedule updated successfully.' : 'Schedule created successfully.';
        emit('saved');
    } catch (requestError) {
        error.value = errorMessage(requestError, 'The schedule could not be saved. Please try again.');
    } finally {
        saving.value = false;
    }
};

const editSchedule = (item) => {
    editingScheduleId.value = item.id;
    form.value = {
        title: item.title,
        date: item.series_date || item.date,
        start_time: normalizeTime(item.start_time),
        end_time: normalizeTime(item.end_time),
        location: item.location || '',
        thumbnail_url: item.thumbnail_url || '',
        notes: item.notes || '',
        assignees: props.canAssign ? item.assignees.map((person) => person.id) : [],
        is_recurring: item.is_recurring,
        recurrence_frequency: item.recurrence_frequency || 'weekly',
        recurrence_interval: item.recurrence_interval || 1,
        recurrence_byweekday: item.recurrence_byweekday || [],
        recurrence_until: item.recurrence_until || '',
    };
    viewMode.value = 'list';
};

watch(
    () => props.scheduleToEdit,
    (item) => {
        if (item) {
            editSchedule(item);
        }
    },
    { immediate: true }
);

const deleteSchedule = async (item) => {
    notice.value = '';
    error.value = '';
    try {
        await window.axios.delete(
            `/${props.entity}/ministries/${props.ministrySlug}/schedules/${item.id}`
        );
        await loadOccurrences();
        notice.value = 'Schedule deleted successfully.';
        emit('deleted');
    } catch (requestError) {
        error.value = errorMessage(requestError, 'The schedule could not be deleted. Please try again.');
    }
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

onMounted(async () => {
    await loadMembers();
    await loadOccurrences();
    await loadMediaImages();
});
</script>
