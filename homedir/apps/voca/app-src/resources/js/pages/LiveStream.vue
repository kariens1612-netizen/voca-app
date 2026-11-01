<template>
    <section class="section broadcast-home">
        <div class="container">
            <div class="broadcast-heading">
                <div>
                    <p class="eyebrow">VOCA • Across the network</p>
                    <h1>Live now</h1>
                    <p class="section-subtitle">Watch services and broadcasts from ministries and organizations. Everyone is welcome to watch.</p>
                </div>
                <RouterLink class="button outline" :to="user ? '/inside' : '/member-login'">{{ user ? 'Go inside' : 'Sign in to go inside' }}</RouterLink>
            </div>
            <p v-if="loading" role="status">Finding live broadcasts…</p>
            <div v-else-if="error" class="card broadcast-empty" role="alert">
                <h2>We couldn’t refresh the broadcasts</h2>
                <p>Please check your connection and try again.</p>
                <button class="button outline" type="button" @click="refresh">Try again</button>
            </div>
            <template v-else>
                <div v-if="selected" class="broadcast-viewer card">
                    <div class="broadcast-player">
                        <iframe v-if="safeUrl(selected.live.embed_url)" :key="selected.id + selected.live.embed_url" :src="safeUrl(selected.live.embed_url)" :title="selected.live.stream_title || selected.name" allow="autoplay; encrypted-media; fullscreen; picture-in-picture" allowfullscreen></iframe>
                        <div v-else class="broadcast-external"><p>This broadcast opens on {{ selected.live.platform || 'the streaming platform' }}.</p><a class="button" :href="safeUrl(selected.live.watch_url)" target="_blank" rel="noopener noreferrer">Watch broadcast ↗</a></div>
                    </div>
                    <div class="broadcast-viewer-caption"><h2>{{ selected.live.stream_title || selected.name }}</h2><p>{{ selected.name }}</p><a v-if="safeUrl(selected.live.watch_url) && safeUrl(selected.live.embed_url)" :href="safeUrl(selected.live.watch_url)" target="_blank" rel="noopener noreferrer">Open on {{ selected.live.platform || 'streaming platform' }} ↗</a><button class="button ghost" type="button" @click="selectedId = null">Close player</button></div>
                </div>
                <div v-if="broadcasts.length" class="broadcast-grid">
                    <article v-for="broadcast in broadcasts" :key="broadcast.id" class="card broadcast-card">
                        <button class="broadcast-thumbnail" type="button" :aria-label="`Watch ${broadcast.name} live`" @click="watchBroadcast(broadcast)">
                            <img v-if="safeUrl(broadcast.thumbnail_url)" :src="safeUrl(broadcast.thumbnail_url)" :alt="broadcast.name" loading="lazy" />
                            <span v-else class="broadcast-initial" aria-hidden="true">{{ broadcast.name.slice(0, 1) }}</span>
                            <span class="broadcast-live">● Live</span><span class="broadcast-play" aria-hidden="true">▶</span>
                        </button>
                        <div class="broadcast-card-body"><p class="eyebrow">{{ broadcast.live.platform }}</p><h2>{{ broadcast.live.stream_title || broadcast.name }}</h2><p>{{ broadcast.name }}</p><p v-if="broadcast.tagline">{{ broadcast.tagline }}</p><button class="button" type="button" @click="watchBroadcast(broadcast)">Watch live</button></div>
                    </article>
                </div>
                <div v-else class="card broadcast-empty" role="status"><p class="eyebrow">Stay connected</p><h2>No broadcasts are live right now</h2><p>Come back for the next service. This page checks for new broadcasts automatically.</p><RouterLink class="button outline" :to="user ? '/inside' : '/member-login'">Explore inside VOCA</RouterLink></div>
                <p class="broadcast-refresh">Broadcasts refresh every 30 seconds. No account is needed to watch.</p>
            </template>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { useAuth } from '../composables/useAuth';
const { user } = useAuth();
const broadcasts = ref([]), loading = ref(true), error = ref(false), selectedId = ref(null);
const selected = computed(() => broadcasts.value.find(item => item.id === selectedId.value));
let timer, pending = false, disposed = false;
const safeUrl = (value) => {
    try { const url = new URL(value); return ['https:', 'http:'].includes(url.protocol) ? url.href : ''; } catch { return ''; }
};
const refresh = async () => {
    if (pending) return;
    pending = true;
    try {
        const { data } = await window.axios.get('/api/public/live-broadcasts', { timeout: 12000 });
        if (!Array.isArray(data?.data)) throw new Error('Invalid broadcast response');
        if (disposed) return;
        broadcasts.value = data.data.filter(item => item.live?.is_live && (safeUrl(item.live.embed_url) || safeUrl(item.live.watch_url)));
        if (!selected.value) selectedId.value = null;
        error.value = false;
    } catch {
        if (!disposed) { error.value = true; broadcasts.value = []; selectedId.value = null; }
    } finally { pending = false; if (!disposed) loading.value = false; }
};
const watchBroadcast = (broadcast) => {
    selectedId.value = broadcast.id;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};
const refreshOnReturn = () => { if (!document.hidden) refresh(); };
onMounted(() => { refresh(); timer = window.setInterval(() => { if (!document.hidden) refresh(); }, 30000); document.addEventListener('visibilitychange', refreshOnReturn); });
onUnmounted(() => { disposed = true; window.clearInterval(timer); document.removeEventListener('visibilitychange', refreshOnReturn); });
</script>

<style scoped>
.broadcast-heading { display: flex; align-items: center; justify-content: space-between; gap: 24px; margin-bottom: 32px; }
.broadcast-heading h1 { font-size: clamp(2.5rem, 6vw, 4.5rem); margin-bottom: 12px; }
.broadcast-heading .section-subtitle { max-width: 650px; }
.broadcast-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr)); gap: 24px; }
.broadcast-card { padding: 0; overflow: hidden; min-width: 0; }
.broadcast-thumbnail { display: block; width: 100%; aspect-ratio: 16/9; position: relative; border: 0; background: #10263f; cursor: pointer; color: white; overflow: hidden; }
.broadcast-thumbnail img { width: 100%; height: 100%; object-fit: cover; }
.broadcast-initial { font-size: 4rem; opacity: .5; }
.broadcast-live { position: absolute; top: 14px; left: 14px; background: #b61425; padding: 5px 12px; border-radius: 20px; font-weight: 700; }
.broadcast-play { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #0009; border-radius: 50%; width: 54px; height: 54px; display: grid; place-items: center; }
.broadcast-card-body, .broadcast-viewer-caption { padding: 24px; display: grid; gap: 12px; }
.broadcast-card h2 { font-size: 1.4rem; overflow-wrap: anywhere; }
.broadcast-card .button { justify-self: start; }
.broadcast-empty { padding: clamp(24px, 5vw, 64px); display: grid; justify-items: start; gap: 20px; }
.broadcast-viewer { padding: 0; overflow: hidden; margin-bottom: 32px; }
.broadcast-player { aspect-ratio: 16/9; background: #081224; }
.broadcast-player iframe { display: block; width: 100%; height: 100%; border: 0; }
.broadcast-external { height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 16px; padding: 20px; text-align: center; }
.broadcast-refresh { margin-top: 24px; font-size: .9rem; opacity: .75; }
@media (max-width: 640px) { .broadcast-heading { align-items: flex-start; flex-direction: column; } .broadcast-home { padding-top: 28px; } }
</style>
