<template>
    <div class="about-page">
        <section v-if="!isVoca" class="section">
            <div class="container"><p class="eyebrow">About {{ tenantName }}</p><h1>{{ tenantName }}</h1><p class="about-lead">{{ brand.description }}</p><RouterLink class="button outline" to="/contact">Contact us</RouterLink></div>
        </section>
        <template v-if="isVoca">
            <section id="pillars" class="section">
                <div class="container">
                    <div class="section-header"><p class="eyebrow">About VOCA</p><h1>The Four Pillars</h1><p v-for="paragraph in about.introduction" :key="paragraph">{{ paragraph }}</p></div>
                    <div class="about-values">
                        <article v-for="pillar in illustratedPillars" :key="pillar.title" class="card">
                            <img :src="pillar.image.src" :alt="pillar.image.alt" width="1672" height="941" loading="lazy" decoding="async" />
                            <h3>{{ pillar.title }}</h3><p>{{ pillar.description }}</p>
                        </article>
                    </div>
                </div>
            </section>
            <section id="significance" class="section section-tight">
                <div class="container"><article class="card about-significance"><h2>The Significance of the Four Corners</h2><p v-for="paragraph in about.significance" :key="paragraph">{{ paragraph }}</p></article></div>
            </section>
            <section id="about" class="section section-tight">
                <div class="container about-intro">
                    <img :src="brandingImage" alt="VOCA brand identity" class="about-photo" loading="lazy" />
                    <div>
                        <h2>{{ voca.statement }}</h2>
                        <p>VOCA exists to inform, inspire and influence Africa with a voice rooted in faith, anchored in truth and committed to unity.</p>
                        <div class="about-purpose">
                            <div><h3>Our Vision</h3><p>{{ voca.vision }}</p></div>
                            <div><h3>Our Mission</h3><p>{{ voca.mission }}</p></div>
                        </div>
                    </div>
                </div>
            </section>
        </template>
    </div>
</template>

<script setup>
import { RouterLink } from 'vue-router';
import { voca, communityImages } from '../data/voca';
import brandingImage from '../../../../images/voca/about-branding.png';
import faithImage from '../../../../images/voca/community/bible-fellowship.webp';
import truthImage from '../../../../images/voca/community/tv-conversation.webp';
import unityImage from '../../../../images/voca/community/terrace-fellowship.webp';
import impactImage from '../../../../images/voca/community/community-volunteers.webp';
import { about } from '../data/about';
import { useTenant } from '../composables/useTenant';

const { isVoca, brand, name: tenantName } = useTenant();
const pillarImages = [
    { ...communityImages.bible, src: faithImage },
    { ...communityImages.tv, src: truthImage },
    { ...communityImages.community, src: unityImage },
    { ...communityImages.volunteers, src: impactImage },
];
const illustratedPillars = about.pillars.map((pillar, index) => ({ ...pillar, image: pillarImages[index] }));
</script>

<style scoped>
.about-intro { display: grid; grid-template-columns: 1.1fr 1fr; gap: clamp(28px, 5vw, 72px); align-items: center; }
h1 { font-family: var(--display-font); font-size: clamp(2.2rem, 4.5vw, 4.5rem); line-height: 1.12; margin: 16px 0 24px; }
.about-lead { font-size: 1.15rem; margin-bottom: 28px; }
p { line-height: 1.75; color: var(--ink-700); }
.about-photo { width: 100%; height: auto; object-fit: contain; border-radius: 24px; }
.about-purpose, .about-values { display: grid; gap: 28px; }
.about-purpose { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.about-values { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.card { padding: 28px; }
.card h2, .card h3 { margin: 12px 0; }
.about-values img { width: 100%; height: auto; aspect-ratio: 16 / 10; object-fit: cover; border-radius: 16px; }
.about-significance p + p { margin-top: 20px; }
.about-intro > *, .about-values > * { min-width: 0; }
section[id] { scroll-margin-top: 100px; }
@media (max-width: 1100px) { .about-values { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 860px) {
    .section { padding: 32px 0; }
    .section-tight { padding-top: 0; }
    .about-intro, .about-purpose, .about-values { grid-template-columns: minmax(0, 1fr); gap: 20px; }
    .card { padding: 22px; }
    .about-lead { font-size: 1rem; }
    .button-row { display: flex; flex-direction: column; align-items: stretch; }
    .button { text-align: center; white-space: normal; }
}
</style>
