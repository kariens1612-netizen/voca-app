<template>
    <section class="announcements" id="announcements">
        <div class="announcements-header">
            <div>
                <h2>Announcements</h2>
                <p>Send updates and reminders to ministry members.</p>
            </div>
            <button v-if="canManage" class="button ghost" type="button" @click="toggleForm">
                {{ showForm ? 'Hide form' : 'Create update' }}
            </button>
        </div>

        <div v-if="canManage && showForm" class="announcements-form">
            <form @submit.prevent="saveAnnouncement">
                <label>
                    Title
                    <input v-model="form.title" type="text" maxlength="160" required />
                </label>
                <label>
                    Message
                    <textarea v-model="form.body" rows="4" maxlength="4000" required></textarea>
                </label>
                <div class="announcements-form-row">
                    <label>
                        Priority
                        <select v-model.number="form.priority">
                            <option :value="0">Normal</option>
                            <option :value="1">High</option>
                            <option :value="2">Urgent</option>
                        </select>
                    </label>
                    <label class="announcements-toggle">
                        <input v-model="form.pinned" type="checkbox" />
                        Pin to top
                    </label>
                </div>

                <div v-if="isSuperadmin" class="announcements-targets">
                    <p class="announcements-targets-title">Audience</p>
                    <label class="announcements-toggle">
                        <input v-model="globalMode" type="radio" value="all" />
                        All ministries
                    </label>
                    <label class="announcements-toggle">
                        <input v-model="globalMode" type="radio" value="selected" />
                        Selected ministries
                    </label>
                    <div v-if="globalMode === 'selected'" class="announcements-select">
                        <p>Choose ministries</p>
                        <div class="announcements-pills">
                            <button
                                v-for="ministry in ministries"
                                :key="ministry.id"
                                class="announcements-pill"
                                :class="{ active: form.ministry_ids.includes(ministry.id) }"
                                type="button"
                                :aria-pressed="form.ministry_ids.includes(ministry.id)"
                                @click="toggleMinistry(ministry.id)"
                            >
                                {{ ministry.name }}
                            </button>
                        </div>
                        <div v-if="!ministries.length" class="announcements-empty">
                            No ministries loaded yet.
                            <button class="button ghost" type="button" @click="reloadMinistries">
                                {{ ministriesLoading ? 'Loading...' : 'Reload ministries' }}
                            </button>
                        </div>
                        <div v-if="ministryError" class="form-error">{{ ministryError }}</div>
                    </div>
                </div>

                <div v-if="error" class="form-error">{{ error }}</div>

                <div class="announcements-actions">
                    <button class="button" type="submit" :disabled="saving">
                        {{ saving ? 'Saving...' : editingId ? 'Update' : 'Post update' }}
                    </button>
                    <button class="button outline" type="button" @click="cancelEdit">
                        {{ editingId ? 'Cancel' : 'Reset' }}
                    </button>
                </div>
            </form>
        </div>

        <div v-if="loading" class="announcements-loading">Loading announcements...</div>
        <div v-else-if="!announcements.length" class="announcements-empty">No announcements yet.</div>
        <div v-else class="announcements-list">
            <article v-for="item in announcements" :key="item.id" class="announcements-item">
                <div class="announcements-item-header">
                    <h3>{{ item.title }}</h3>
                    <span class="announcements-badge" :class="{ global: item.is_global }">
                        {{ badgeLabel(item) }}
                    </span>
                </div>
                <p class="announcements-body">{{ item.body }}</p>
                <div class="announcements-meta">
                    <span>{{ formatDate(item.created_at) }}</span>
                    <span v-if="item.author?.name">by {{ item.author.name }}</span>
                    <span
                        v-if="item.is_global && !item.target_all && item.target_ministries?.length"
                    >
                        Targets: {{ item.target_ministries.map((ministry) => ministry.name).join(', ') }}
                    </span>
                </div>
                <div v-if="canManage" class="announcements-item-actions">
                    <button class="button ghost" type="button" @click="startEdit(item)">Edit</button>
                    <button class="button outline" type="button" @click="deleteAnnouncement(item)">
                        Delete
                    </button>
                </div>
            </article>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useMinistries } from '../composables/useMinistries';

const props = defineProps({
    entity: { type: String, required: true },
    ministrySlug: { type: String, required: true },
    canManage: { type: Boolean, default: false },
    isSuperadmin: { type: Boolean, default: false },
});

const { ministriesByEntity, loadEntity } = useMinistries();

const announcements = ref([]);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const showForm = ref(false);
const globalMode = ref('all');
const editingId = ref(null);
const ministriesLoading = ref(false);
const ministryError = ref('');
const ministryOptions = ref([]);

const form = ref({
    title: '',
    body: '',
    pinned: false,
    priority: 0,
    ministry_ids: [],
});

const ministries = computed(() => {
    if (ministryOptions.value.length) {
        return ministryOptions.value;
    }
    return ministriesByEntity.value[props.entity] || [];
});

const loadAnnouncements = async () => {
    loading.value = true;
    try {
        const response = await window.axios.get(
            `/${props.entity}/ministries/${props.ministrySlug}/announcements`
        );
        announcements.value = response.data.announcements || [];
    } catch (err) {
        announcements.value = [];
    } finally {
        loading.value = false;
    }
};

const resetForm = () => {
    form.value = { title: '', body: '', pinned: false, priority: 0, ministry_ids: [] };
    globalMode.value = 'all';
    error.value = '';
    editingId.value = null;
};

const toggleForm = () => {
    showForm.value = !showForm.value;
};

const reloadMinistries = async () => {
    ministriesLoading.value = true;
    ministryError.value = '';
    try {
        const response = await window.axios.get(`/${props.entity}/ministries`);
        const items = response.data?.ministries || [];
        ministryOptions.value = items;
        ministriesByEntity.value[props.entity] = items;
        if (!items.length) {
            ministryError.value = 'No ministries returned from the server.';
        }
    } catch (err) {
        ministryError.value = err?.response?.data?.message || 'Unable to load ministries.';
    } finally {
        ministriesLoading.value = false;
    }
};

const toggleMinistry = (ministryId) => {
    if (form.value.ministry_ids.includes(ministryId)) {
        form.value.ministry_ids = form.value.ministry_ids.filter((id) => id !== ministryId);
        return;
    }
    form.value.ministry_ids = [...form.value.ministry_ids, ministryId];
};

const cancelEdit = () => {
    resetForm();
    showForm.value = false;
};

const startEdit = (item) => {
    showForm.value = true;
    editingId.value = item.id;
    form.value = {
        title: item.title,
        body: item.body,
        pinned: Boolean(item.pinned),
        priority: Number(item.priority ?? 0),
        ministry_ids: (item.target_ministries || []).map((ministry) => ministry.id),
    };

    if (props.isSuperadmin) {
        globalMode.value = item.target_all ? 'all' : 'selected';
    } else {
        globalMode.value = 'all';
    }
};

const saveAnnouncement = async () => {
    error.value = '';

    if (props.isSuperadmin && globalMode.value === 'selected' && !form.value.ministry_ids.length) {
        error.value = 'Select at least one ministry.';
        return;
    }

    saving.value = true;
    try {
        const payload = {
            title: form.value.title,
            body: form.value.body,
            pinned: form.value.pinned,
            priority: form.value.priority,
            target_all: props.isSuperadmin ? globalMode.value === 'all' : false,
            ministry_ids:
                props.isSuperadmin && globalMode.value === 'selected'
                    ? form.value.ministry_ids
                    : [],
        };

        if (editingId.value) {
            await window.axios.patch(
                `/${props.entity}/ministries/${props.ministrySlug}/announcements/${editingId.value}`,
                payload
            );
        } else {
            await window.axios.post(
                `/${props.entity}/ministries/${props.ministrySlug}/announcements`,
                payload
            );
        }

        await loadAnnouncements();

        resetForm();
        showForm.value = false;
    } catch (err) {
        error.value = err?.response?.data?.message || 'Unable to post announcement.';
    } finally {
        saving.value = false;
    }
};

const deleteAnnouncement = async (item) => {
    if (!confirm('Delete this announcement?')) {
        return;
    }

    try {
        await window.axios.delete(
            `/${props.entity}/ministries/${props.ministrySlug}/announcements/${item.id}`
        );
        announcements.value = announcements.value.filter((entry) => entry.id !== item.id);
    } catch (err) {
        error.value = err?.response?.data?.message || 'Unable to delete announcement.';
    }
};

const badgeLabel = (item) => {
    const scope = item.is_global ? (item.target_all ? 'Global' : 'Selected ministries') : 'Ministry';
    const priority =
        Number(item.priority) === 2 ? 'Urgent' : Number(item.priority) === 1 ? 'High' : 'Normal';
    return item.pinned ? `${scope} · Pinned · ${priority}` : `${scope} · ${priority}`;
};

const formatDate = (value) => {
    if (!value) {
        return '';
    }
    const date = new Date(value);
    return date.toLocaleString();
};

onMounted(async () => {
    await loadAnnouncements();

    if (!ministriesByEntity.value[props.entity]) {
        await loadEntity(props.entity, { force: true });
    }
});

watch(
    () => [props.entity, props.isSuperadmin, globalMode.value],
    async () => {
        if (globalMode.value !== 'selected') {
            return;
        }
        if (!ministriesByEntity.value[props.entity] || !ministriesByEntity.value[props.entity].length) {
            await reloadMinistries();
        }
    }
);
</script>
