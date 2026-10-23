<template>
    <div>
        <section class="section live-page">
            <div class="container">
                <div class="section-header">
                    <p class="eyebrow">Live</p>
                    <h1>VOCA Live Stream</h1>
                    <p class="section-subtitle">
                        OBS-ready streaming for services and special events, with a larger viewing experience and a
                        simple operator checklist for the media team.
                    </p>
                    <div class="live-badges">
                        <span>{{ liveStatusLabel }}</span>
                        <span>{{ livePlatformLabel }}</span>
                        <span>{{ liveScheduleLabel }}</span>
                    </div>
                    <div class="section-actions">
                        <RouterLink class="button outline" to="/">Back to home</RouterLink>
                        <a v-if="watchUrl" class="button ghost" :href="watchUrl" target="_blank" rel="noreferrer">
                            Open stream destination
                        </a>
                    </div>
                </div>
                <div class="live-page-grid">
                    <div class="live-stage" @click="requestFullscreen">
                        <iframe
                            v-if="liveEmbedUrl"
                            :src="liveEmbedUrl"
                            title="VOCA Live Stream"
                            allow="fullscreen"
                            allowfullscreen
                            loading="lazy"
                        ></iframe>
                        <div v-else class="tv-placeholder">
                            <p>
                                Add your public player embed from the admin portal stream settings section, or use the
                                fallback config file if the database has not been set up yet.
                            </p>
                            <p class="tv-placeholder-note">
                                OBS sends the stream to YouTube, Facebook, Vimeo, Rumble, or another RTMP destination.
                                The website then embeds that public player.
                            </p>
                        </div>
                        <button class="fullscreen-button" type="button" @click.stop="requestFullscreen">
                            Full screen
                        </button>
                    </div>
                    <div class="live-info-card">
                        <p class="eyebrow">OBS workflow</p>
                        <h3>How this site works with OBS</h3>
                        <p>
                            Use OBS to broadcast to your streaming platform, then place that platform's public embed on
                            this page for viewers. Superadmin and Power Media admins can update these links directly
                            from the portal. Keep the OBS stream key private and out of Vite env files.
                        </p>
                        <div class="live-info-stack">
                            <div class="live-info-row">
                                <span>Platform</span>
                                <strong>{{ livePlatformLabel }}</strong>
                            </div>
                            <div class="live-info-row">
                                <span>Public watch page</span>
                                <a v-if="watchUrl" :href="watchUrl" target="_blank" rel="noreferrer">Open destination</a>
                                <strong v-else>Not set yet</strong>
                            </div>
                            <div class="live-info-row">
                                <span>Embed source</span>
                                <strong>{{ liveEmbedUrl ? 'Configured' : 'Missing' }}</strong>
                            </div>
                        </div>
                        <div class="live-info-actions">
                            <RouterLink class="button ghost" to="/#live">Back to home livestream</RouterLink>
                            <RouterLink class="button" to="/international">Explore global family</RouterLink>
                        </div>
                    </div>
                </div>

                <div class="obs-grid">
                    <article class="obs-card">
                        <p class="eyebrow">Before going live</p>
                        <h3>OBS quick checklist</h3>
                        <ul class="obs-checklist">
                            <li v-for="item in obsChecklist" :key="item">{{ item }}</li>
                        </ul>
                    </article>

                    <article class="obs-card">
                        <p class="eyebrow">Frontend config</p>
                        <h3>Public settings this app can safely use</h3>
                        <div class="obs-config-list">
                            <div v-for="item in publicConfig" :key="item.key" class="obs-config-row">
                                <code>{{ item.key }}</code>
                                <span>{{ item.value }}</span>
                            </div>
                        </div>
                    </article>

                    <article class="obs-card obs-card-wide">
                        <p class="eyebrow">Important</p>
                        <h3>Keep private OBS credentials out of the browser</h3>
                        <p>
                            Never place OBS stream keys in <code>VITE_*</code> variables. Anything prefixed with
                            <code>VITE_</code> is shipped to every visitor. Store private streaming credentials only in
                            OBS itself or in server-side environment variables that are never exposed to the client.
                        </p>
                        <div class="live-info-actions">
                            <a
                                v-if="watchUrl"
                                class="button"
                                :href="watchUrl"
                                target="_blank"
                                rel="noreferrer"
                            >
                                Open public stream page
                            </a>
                        </div>
                    </article>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { liveStreamConfig } from '../config/liveStream';

const remoteSettings = ref(null);

const liveEmbedUrl = computed(
    () =>
        remoteSettings.value?.embed_url
        || liveStreamConfig.embedUrl
        || import.meta.env.VITE_LIVE_EMBED_URL
        || ''
);
const watchUrl = computed(
    () => remoteSettings.value?.watch_url || liveStreamConfig.watchUrl || import.meta.env.VITE_LIVE_WATCH_URL || ''
);
const livePlatform = computed(
    () => remoteSettings.value?.platform || liveStreamConfig.platform || import.meta.env.VITE_LIVE_PLATFORM || 'Rumble'
);
const liveStatus = computed(
    () => remoteSettings.value?.status || liveStreamConfig.status || import.meta.env.VITE_LIVE_STATUS || 'Stand by'
);
const liveSchedule = computed(
    () =>
        remoteSettings.value?.schedule
        || liveStreamConfig.schedule
        || import.meta.env.VITE_LIVE_SCHEDULE
        || 'Sunday 09H30'
);

const livePlatformLabel = computed(() => livePlatform.value);
const liveStatusLabel = computed(() => `Status: ${liveStatus.value}`);
const liveScheduleLabel = computed(() => `Schedule: ${liveSchedule.value}`);

const obsChecklist = [
    'Open the Voca OBS scene collection and confirm audio meters are moving.',
    'Paste the stream key inside OBS, not inside this website.',
    'Start streaming in OBS first, then verify the destination platform receives signal.',
    'Refresh this page and test the embedded player before service starts.',
    'Use full screen mode on mobile for the clearest viewing experience.',
];

const publicConfig = computed(() => [
    { key: 'Embed URL', value: liveEmbedUrl.value || 'Not configured' },
    { key: 'Watch URL', value: watchUrl.value || 'Not configured' },
    { key: 'Platform', value: livePlatform.value },
    { key: 'Status', value: liveStatus.value },
    { key: 'Schedule', value: liveSchedule.value },
]);

const loadSettings = async () => {
    try {
        const response = await window.axios.get('/live-settings');
        const data = response.data || {};

        if (data.embed_url || data.watch_url || data.platform || data.status || data.schedule) {
            remoteSettings.value = data;
        }
    } catch (error) {
        // Keep the frontend fallback values when the database settings are not available yet.
    }
};

const requestFullscreen = (event) => {
    const target = event?.currentTarget || event?.target;
    const container = target?.closest('.live-stage');
    if (!container) {
        return;
    }
    if (document.fullscreenElement) {
        document.exitFullscreen?.();
        document.webkitExitFullscreen?.();
        return;
    }
    container.requestFullscreen?.();
    container.webkitRequestFullscreen?.();
};

onMounted(() => {
    loadSettings();
});
</script>
