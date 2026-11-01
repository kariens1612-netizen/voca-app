<template>
    <section class="section affiliates-page" :class="{ 'affiliates-mobile': isMobile }">
        <div class="container">
            <header class="section-header"><p class="eyebrow">Voice of Christian Africa</p><h1>Members</h1><RouterLink v-if="route.params.slug || route.params.group" to="/affiliates">← All members</RouterLink></header>
            <p v-if="loading" role="status">Loading members…</p>
            <div v-else-if="error" role="alert" class="card"><p>{{ error }}</p><button class="button outline" @click="load">Try again</button></div>
            <div v-else class="affiliate-sections">
                <section v-for="(section, index) in sections" :id="section.id || (section.cards.some(card => card.title === 'Businesses') ? 'affiliate-pathways' : undefined)" :key="index" class="affiliate-section">
                    <AffiliateContentBlock :block="section" />
                    <div v-if="section.cards.length" class="affiliate-grid">
                        <article v-for="(card, cardIndex) in section.cards" :key="cardIndex" class="card"><AffiliateContentBlock :block="card" card /></article>
                    </div>
                </section>
                <div class="card affiliate-join"><h2>Join the VOCA community</h2><RouterLink class="button" to="/register">Register</RouterLink></div>
            </div>
        </div>
    </section>
</template>
<script setup>
import { ref, watch, nextTick } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import AffiliateContentBlock from '../components/AffiliateContentBlock.vue';
import { useMobileLayout } from '../composables/useMobileLayout';
const route = useRoute();
const { isMobile } = useMobileLayout();
const sections = ref([]), loading = ref(true), error = ref('');
let requestId = 0;
const load = async () => {
    const id = ++requestId;
    loading.value = true; error.value = ''; sections.value = [];
    const endpoint = route.params.group ? '/api/voca/affiliate-pathways/' + encodeURIComponent(route.params.group) : '/api/voca/affiliates' + (route.params.slug ? '/' + encodeURIComponent(route.params.slug) : '');
    try {
        const { data } = await window.axios.get(endpoint);
        if (id !== requestId) return;
        if (!Array.isArray(data.sections) || !data.sections.length) throw new Error('No content');
        sections.value = data.sections;
    } catch (e) {
        if (id !== requestId) return;
        error.value = e.response?.status === 404 ? 'This member profile is not available.' : 'Member information is temporarily unavailable. Please try again.';
    } finally {
        if (id === requestId) {
            loading.value = false;
            await nextTick();
            if (route.hash) document.getElementById(route.hash.slice(1))?.scrollIntoView();
        }
    }
};
watch(() => route.path, load, { immediate: true });
</script>
<style scoped>
h1 { font-family: var(--display-font); font-size: clamp(2.2rem, 5vw, 4rem); margin: 12px 0; }
.affiliate-sections { display: grid; gap: 64px; }
.affiliate-section { display: grid; gap: 28px; scroll-margin-top: 220px; }
.affiliate-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 28px; }
.affiliate-grid .card { padding: 28px; }
.affiliate-join { display: grid; justify-items: start; gap: 24px; padding: 32px; }
@media (max-width: 1100px) { .affiliate-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
.affiliates-mobile { padding: 28px 0; }
.affiliates-mobile .affiliate-grid { grid-template-columns: minmax(0, 1fr); gap: 20px; }
.affiliates-mobile .affiliate-sections { gap: 36px; }
.affiliates-mobile .affiliate-grid .card { padding: 22px; }
</style>
