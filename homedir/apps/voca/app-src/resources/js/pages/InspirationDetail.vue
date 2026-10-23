<template>
    <main class="section inspiration-detail"><article v-if="item" class="container inspiration-article">
        <RouterLink class="back-link" to="/inspiration">← Back to Inspiration</RouterLink>
        <span class="content-type">{{ label }}</span><h1>{{ item.title }}</h1>
        <p class="article-summary">{{ item.summary }}</p>
        <div class="content-meta"><span>{{ item.author }}</span><span>{{ item.ministry?.name }}</span><span>{{ displayDate }}</span></div>
        <img v-if="item.media?.cover" class="article-cover" :src="item.media.cover.url" :alt="item.title">
        <blockquote v-if="item.scripture_reference" class="scripture-reference">{{ item.scripture_reference }} <small>{{ item.scripture_translation }}</small></blockquote>
        <iframe v-if="item.media?.video?.embed_url" class="article-video" :src="item.media.video.embed_url" title="Encouragement video" allowfullscreen></iframe>
        <video v-else-if="item.media?.video?.url && item.media.video.name" class="article-video" controls preload="metadata"><source :src="item.media.video.url" :type="item.media.video.mime_type"></video>
        <div class="article-body" v-html="item.body"></div>
        <a v-if="item.media?.pdf" class="button" :href="item.media.pdf.url" target="_blank" rel="noopener">Read or download newsletter PDF</a>
    </article><div v-else class="container"><p v-if="error" class="builder-error">{{ error }}</p></div></main>
</template>
<script setup>
import { computed, onMounted, ref } from 'vue'; import { RouterLink, useRoute } from 'vue-router';
const route = useRoute(); const item = ref(null); const error = ref('');
const labels = { scripture: 'Scripture', newsletter: 'Newsletter', prophetic_word: 'Prophetic word', encouragement: 'Encouragement', video: 'Video' };
const label = computed(() => labels[item.value?.type] || '');
const displayDate = computed(() => item.value?.published_at ? new Intl.DateTimeFormat('en-ZA', { dateStyle: 'long' }).format(new Date(item.value.published_at)) : '');
onMounted(async () => { try { item.value = (await window.axios.get(`/inspiration/content/${route.params.slug}`)).data; } catch { error.value = 'This content is unavailable or requires member access.'; } });
</script>
