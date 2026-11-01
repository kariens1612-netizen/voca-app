<template>
    <div class="affiliate-copy">
        <div v-if="block.images.length" class="affiliate-images"><img v-for="image in block.images" :key="image.src" :src="image.src" :alt="wording(image.alt)" loading="lazy" /></div>
        <component :is="card ? 'h3' : 'h2'" v-if="block.title">{{ wording(block.title) }}</component>
        <p v-for="(paragraph, index) in block.paragraphs" :key="index">{{ wording(paragraph) }}</p>
        <div v-if="block.links.length" class="affiliate-links">
            <template v-for="(link, index) in block.links" :key="index">
                <RouterLink v-if="internalPath(link.url)" :to="internalPath(link.url)">{{ wording(link.label) }}</RouterLink>
                <a v-else :href="link.url" target="_blank" rel="noopener noreferrer">{{ wording(link.label) }} <span aria-hidden="true">↗</span></a>
            </template>
        </div>
    </div>
</template>
<script setup>
import { RouterLink } from 'vue-router';
defineProps({ block: { type: Object, required: true }, card: Boolean });
const wording = text => text;
const internalPath = url => {
    if (url.startsWith('/affiliates')) return url;
    try {
        const target = new URL(url);
        if (!['voca.africa', 'www.voca.africa'].includes(target.hostname)) return null;
        let path = target.pathname.replace(/\/$/, '');
        if (path === '/members') path = '/affiliates';
        else if (path.startsWith('/members/')) path = path.replace('/members/', '/affiliates/pathways/');
        else if (!/^\/(affiliates(?:\/[^/]+)?|register|member-login|login|directory|about-us)$/.test(path)) {
            if (path === '' && target.hash === '#members') return '/affiliates#affiliate-pathways';
            return null;
        }
        return path + target.search + target.hash;
    } catch { return null; }
};
</script>
<style scoped>
.affiliate-copy { display: grid; gap: 16px; min-width: 0; }
.affiliate-copy p { line-height: 1.75; color: var(--ink-700); white-space: pre-line; overflow-wrap: anywhere; }
.affiliate-copy h2 { font-size: clamp(1.6rem, 3vw, 2.6rem); }
.affiliate-copy h3 { font-size: 1.3rem; }
.affiliate-images { display: flex; gap: 16px; flex-wrap: wrap; }
.affiliate-images img { width: 100%; max-height: 420px; object-fit: contain; border-radius: 18px; }
.affiliate-links { display: flex; flex-wrap: wrap; gap: 12px 24px; }
.affiliate-links a { color: var(--gold-400); padding: 8px 0; line-height: 1.5; }
</style>
