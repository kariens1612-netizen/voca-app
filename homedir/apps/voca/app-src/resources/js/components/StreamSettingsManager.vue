<template>
    <section v-if="canManage" class="stream-settings portal-section" id="stream-settings">
        <div class="stream-settings-header">
            <div>
                <h2>Live stream settings</h2>
                <p class="stream-settings-subtitle">
                    Update the Rumble stream details shown on the public live page.
                </p>
            </div>
            <button class="button ghost" type="button" @click="loadSettings">
                {{ loading ? 'Refreshing...' : 'Refresh settings' }}
            </button>
        </div>

        <div class="stream-settings-grid">
            <form class="stream-settings-form" @submit.prevent="saveSettings">
                <label>
                    Platform
                    <input v-model="form.platform" type="text" maxlength="80" required />
                </label>
                <label>
                    Status
                    <input v-model="form.status" type="text" maxlength="80" required />
                </label>
                <label>
                    Schedule
                    <input v-model="form.schedule" type="text" maxlength="120" required />
                </label>
                <label>
                    Stream title
                    <input v-model="form.stream_title" type="text" maxlength="160" />
                </label>
                <label class="stream-settings-span-2">
                    Rumble embed URL
                    <input v-model="form.embed_url" type="url" maxlength="2000" placeholder="Use the Embed button on the Rumble livestream" />
                </label>
                <label class="stream-settings-span-2">
                    Rumble stream URL
                    <input v-model="form.watch_url" type="url" maxlength="2000" placeholder="https://rumble.com/v7cy4ji-kingdom-eyes-kingdom-victory.html" />
                </label>
                <label class="stream-settings-span-2">
                    API or stream key
                    <input v-model="form.api_key" type="password" maxlength="2000" autocomplete="new-password" :placeholder="apiKeyPlaceholder" />
                    <small>This secret is encrypted and is never shown on the public website. Leave blank to keep the saved key.</small>
                </label>
                <label class="stream-settings-span-2">
                    Notes for the stream team
                    <textarea v-model="form.notes" rows="4" maxlength="4000"></textarea>
                </label>

                <div v-if="error" class="form-error">{{ error }}</div>
                <div v-if="success" class="success">{{ success }}</div>

                <div class="stream-settings-actions stream-settings-span-2">
                    <button class="button" type="submit" :disabled="saving">
                        {{ saving ? 'Saving...' : 'Save stream settings' }}
                    </button>
                </div>
            </form>

            <aside class="stream-settings-preview">
                <p class="portal-tag">Live page preview</p>
                <h3>{{ form.stream_title || 'Voca Ministries Live Stream' }}</h3>
                <div class="stream-settings-meta">
                    <span>{{ form.platform || 'Rumble' }}</span>
                    <span>{{ form.status || 'Stand by' }}</span>
                    <span>{{ form.schedule || 'Sunday 09H30' }}</span>
                </div>
                <p class="stream-settings-preview-copy">
                    These values feed the public <code>/live</code> page so viewers always see the latest stream links.
                </p>
                <div class="stream-settings-preview-links">
                    <div>
                        <strong>Embed URL</strong>
                        <span>{{ form.embed_url || 'Not configured yet' }}</span>
                    </div>
                    <div>
                        <strong>Watch URL</strong>
                        <span>{{ form.watch_url || 'Not configured yet' }}</span>
                    </div>
                    <div v-if="lastUpdatedLabel">
                        <strong>Last updated</strong>
                        <span>{{ lastUpdatedLabel }}</span>
                    </div>
                </div>
            </aside>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';

const props = defineProps({
    canManage: { type: Boolean, default: false },
});

const loading = ref(false);
const saving = ref(false);
const error = ref('');
const success = ref('');
const meta = ref({
    updated_at: '',
    updated_by: null,
    has_api_key: false,
});

const form = reactive({
    platform: 'Rumble',
    status: 'Stand by',
    schedule: 'Sunday 09H30',
    embed_url: '',
    watch_url: 'https://rumble.com/v7cy4ji-kingdom-eyes-kingdom-victory.html',
    api_key: '',
    stream_title: 'Voca Ministries Live Stream',
    notes: '',
});

const normalize = (payload = {}) => {
    form.platform = payload.platform || 'Rumble';
    form.status = payload.status || 'Stand by';
    form.schedule = payload.schedule || 'Sunday 09H30';
    form.embed_url = payload.embed_url || '';
    form.watch_url = payload.watch_url || 'https://rumble.com/v7cy4ji-kingdom-eyes-kingdom-victory.html';
    form.api_key = '';
    form.stream_title = payload.stream_title || 'Voca Ministries Live Stream';
    form.notes = payload.notes || '';
    meta.value = {
        updated_at: payload.updated_at || '',
        updated_by: payload.updated_by || null,
        has_api_key: Boolean(payload.has_api_key),
    };
};

const loadSettings = async () => {
    if (!props.canManage) {
        return;
    }

    loading.value = true;
    error.value = '';
    success.value = '';

    try {
        const response = await window.axios.get('/brackenfell/stream-settings');
        normalize(response.data);
    } catch (err) {
        error.value = err?.response?.data?.message || 'Unable to load stream settings.';
    } finally {
        loading.value = false;
    }
};

const saveSettings = async () => {
    saving.value = true;
    error.value = '';
    success.value = '';

    try {
        const response = await window.axios.patch('/brackenfell/stream-settings', {
            platform: form.platform,
            status: form.status,
            schedule: form.schedule,
            embed_url: form.embed_url,
            watch_url: form.watch_url || null,
            stream_title: form.stream_title || null,
            notes: form.notes || null,
            api_key: form.api_key || null,
        });

        normalize(response.data);
        success.value = 'Stream settings updated successfully.';
    } catch (err) {
        error.value = err?.response?.data?.message || 'Unable to save stream settings.';
    } finally {
        saving.value = false;
    }
};

const lastUpdatedLabel = computed(() => {
    if (!meta.value.updated_at) {
        return '';
    }

    const updated = new Date(meta.value.updated_at);
    const author = meta.value.updated_by?.name ? ` by ${meta.value.updated_by.name}` : '';
    return `${updated.toLocaleString()}${author}`;
});

const apiKeyPlaceholder = computed(() => (
    meta.value.has_api_key ? 'Saved securely — enter a new key to replace it' : 'Paste the Rumble API or stream key'
));

onMounted(() => {
    loadSettings();
});
</script>
