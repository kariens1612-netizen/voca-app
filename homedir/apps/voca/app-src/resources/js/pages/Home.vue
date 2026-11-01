<template>
    <OrganisationHome translate="no" v-if="tenant && tenant.slug !== 'voca'" />
    <MobileHome v-else-if="isMobile" />
    <div v-else class="website-home">
        <section class="website-hero">
            <div class="container website-hero-grid">
                <div class="website-hero-copy">
                    <p class="eyebrow">{{ voca.subtitle }}</p>
                    <h1>Africa Needs A Trusted Voice.</h1>
                    <p class="lead">{{ voca.tagline }}</p>
                    <p>Informing, Inspiring, and Influencing Africa with a voice rooted in Biblical truth, covenant unity, and continental impact.</p>
                    <p>Connect with likeminded believers in ministry, business and life through an Apostolic Resource Centre serving Africa.</p>
                    <p class="website-hero-quote">Africa’s story deserves to be told with excellence.</p>
                    <div class="website-hero-actions">
                        <RouterLink class="button" :to="voca.loginUrl">Member Login</RouterLink>
                        <RouterLink class="button outline" :to="voca.registrationUrl">Register</RouterLink>
                        <RouterLink class="button outline" to="/affiliates">Become a Member</RouterLink>
                        <RouterLink class="button ghost" to="/login">Ministry Team Portal</RouterLink>
                    </div>
                    <div class="website-hero-tags"><span v-for="pillar in pillars" :key="pillar.title">{{ pillar.title }}</span></div>
                </div>
                <article class="website-hero-visual">
                    <img :src="heroImageUrl" :alt="communityImages.community.alt" loading="eager" fetchpriority="high" />
                    <div class="website-hero-overlay">
                        <span class="card-label">{{ voca.subtitle }}</span>
                        <h3>{{ voca.statement }}</h3>
                        <p>Truth builds trust, hope transforms communities, and informed people shape nations.</p>
                    </div>
                </article>
            </div>
        </section>
        <section id="pillars" class="section section-tight">
            <div class="container">
                <div class="section-header centered"><p class="eyebrow">Our Identity</p><h2>The Four Pillars</h2><p class="section-subtitle">Faith, Truth, Unity and Impact are four corners of one structure. Each is essential to the VOCA platform.</p></div>
                <div class="website-ministry-grid">
                    <article v-for="pillar in pillars" :key="pillar.title" class="website-ministry-card card"><img class="voca-card-image" :src="pillar.image.src" :alt="pillar.image.alt" width="1672" height="941" loading="lazy" decoding="async" /><h3>{{ pillar.title }}</h3><p>{{ pillar.description }}</p></article>
                </div>
                <RouterLink class="button outline" to="/about-us#pillars">Explore Our Values</RouterLink>
            </div>
        </section>
        <section id="about" class="section voca-blue-section">
            <div class="container">
                <div class="section-header"><p class="eyebrow">About VOCA</p><h2>{{ voca.statement }}</h2><p class="section-subtitle">VOCA exists to inform, inspire and influence Africa with a voice rooted in faith, anchored in truth and committed to unity.</p></div>
                <div class="website-support-grid">
                    <article class="website-support-card card"><h3>Our Vision</h3><p>{{ voca.vision }}</p></article>
                    <article class="website-support-card card"><h3>Our Mission</h3><p>{{ voca.mission }}</p></article>
                </div>
                <RouterLink class="button outline" to="/about-us">About Us</RouterLink>
            </div>
        </section>
        <div class="container"><EthneIntro /></div>
        <section id="media" class="section section-tight voca-blue-section">
            <div class="container">
                <div class="section-header"><p class="eyebrow">Media and Services</p><h2>VOCA Platform</h2></div>
                <div class="website-ministry-grid">
                    <article v-for="platform in platforms" :key="platform.title" class="website-ministry-card card">
                        <img class="voca-card-image" :src="platform.image.src" :alt="platform.image.alt" width="1672" height="941" loading="lazy" decoding="async" /><h3>{{ platform.title }}</h3><p>{{ platform.description }}</p>
                        <RouterLink v-if="platform.to" class="button outline" :to="platform.to">Explore {{ platform.title }}</RouterLink>
                        <a v-else class="button outline" :href="platform.url">Explore {{ platform.title }}</a>
                    </article>
                </div>
            </div>
        </section>
        <section class="section section-tight">
            <div class="container">
                <div class="section-header">
                    <p class="eyebrow">VOCA Network</p>
                    <h2>Connect with the VOCA ministry network.</h2>
                    <p class="section-subtitle">
                        See who is part of the VOCA network, open their live broadcast, and connect with them on
                        their social platforms.
                    </p>
                </div>

                <p v-if="directoryLoading" class="public-directory-empty">Loading ministries...</p>

                <div v-else-if="publicMinistries.length">
                    <div v-if="publicStreams.length" class="public-stream-grid">
                        <article
                            v-for="ministry in publicStreams"
                            :key="`stream-${ministry.id}`"
                            class="public-stream-card"
                            :style="ministryCardStyle(ministry)"
                        >
                            <div class="public-stream-player">
                                <iframe
                                    v-if="ministry.live.embed_url"
                                    :src="ministry.live.embed_url"
                                    :title="ministry.live.stream_title || `${ministry.name} live stream`"
                                    allow="fullscreen; autoplay; encrypted-media; picture-in-picture"
                                    allowfullscreen
                                    loading="lazy"
                                ></iframe>
                                <img
                                    v-else
                                    :src="ministry.thumbnail_url || fallbackMinistryImageUrl"
                                    :alt="ministry.live.stream_title || ministry.name"
                                    loading="lazy"
                                />
                            </div>
                            <div class="public-stream-body">
                                <div>
                                    <p class="card-label">{{ ministry.live.platform || 'Rumble' }}</p>
                                    <h3>{{ ministry.live.stream_title || ministry.name }}</h3>
                                </div>
                                <div class="public-stream-meta">
                                    <span>{{ ministry.live.status || 'Stand by' }}</span>
                                    <span v-if="ministry.live.schedule">{{ ministry.live.schedule }}</span>
                                </div>
                                <div class="public-ministry-actions">
                                    <RouterLink v-if="ministry.slug === 'voca'" class="button" to="/live">
                                        Watch on site
                                    </RouterLink>
                                    <a
                                        v-if="ministry.live.watch_url"
                                        class="button ghost"
                                        :href="ministry.live.watch_url"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        Open destination
                                    </a>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div class="public-ministry-grid">
                    <article
                        v-for="ministry in publicMinistries"
                        :key="ministry.id"
                        class="public-ministry-card card"
                        :style="ministryCardStyle(ministry)"
                    >
                        <a
                            class="public-ministry-thumbnail"
                            :class="{ live: ministry.live.is_live }"
                            :href="ministry.live.watch_url || '#'"
                            target="_blank"
                            rel="noopener"
                            :aria-disabled="!ministry.live.watch_url"
                            @click="handleLiveClick($event, ministry)"
                        >
                            <img :src="ministry.thumbnail_url || fallbackMinistryImageUrl" :alt="ministry.name" loading="lazy" />
                            <span v-if="ministry.live.is_live" class="live-ribbon">Currently live</span>
                            <span v-else class="live-ribbon muted">{{ ministry.live.status }}</span>
                        </a>

                        <div class="public-ministry-body">
                            <div class="public-ministry-heading">
                                <img v-if="ministry.logo_url" :src="ministry.logo_url" :alt="`${ministry.name} logo`" loading="lazy" />
                                <span v-else>{{ ministry.name.slice(0, 1) }}</span>
                                <div>
                                    <p class="card-label">{{ ministry.live.platform || 'Rumble' }}</p>
                                    <h3>{{ ministry.name }}</h3>
                                </div>
                            </div>

                            <p v-if="ministry.tagline" class="public-ministry-tagline">{{ ministry.tagline }}</p>
                            <p>{{ ministry.description || 'Ministry information will be added soon.' }}</p>

                            <div v-if="ministry.ministries.length" class="public-ministry-tags">
                                <span v-for="item in ministry.ministries.slice(0, 3)" :key="item.slug">{{ item.name }}</span>
                            </div>

                            <div class="public-ministry-actions">
                                <a
                                    class="button"
                                    :class="{ ghost: !ministry.live.is_live }"
                                    :href="ministry.live.watch_url || '#'"
                                    target="_blank"
                                    rel="noopener"
                                    @click="handleLiveClick($event, ministry)"
                                >
                                    {{ ministry.live.is_live ? 'Watch live' : 'Open broadcast' }}
                                </a>
                            </div>

                            <div v-if="ministry.social_links.length" class="public-social-links">
                                <a
                                    v-for="link in ministry.social_links"
                                    :key="`${ministry.id}-${link.platform}`"
                                    :href="link.url"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    {{ link.platform }}
                                </a>
                            </div>
                        </div>
                    </article>
                    </div>
                </div>

                <p v-else class="public-directory-empty">Ministry information will appear here as ministries are published.</p>
            </div>
        </section>

        <section id="what-we-do" class="section">
            <div class="container">
                <div class="section-header"><p class="eyebrow">Connect and Participate</p><h2>Your place in the VOCA network.</h2></div>
                <div class="website-ministry-grid">
                    <component :is="pathway.to ? RouterLink : 'a'" v-for="pathway in pathways" :key="pathway.title" class="website-ministry-card card card-link" :to="pathway.to" :href="pathway.to ? undefined : pathway.url"><img class="voca-card-image" :src="pathway.image.src" :alt="pathway.image.alt" width="1672" height="941" loading="lazy" decoding="async" /><h3>{{ pathway.title }}</h3><p>{{ pathway.description }}</p><span class="ministry-card-link">Explore {{ pathway.title }}</span></component>
                </div>
            </div>
        </section>
        <section id="contact" class="section alt">
            <div class="container">
                <div class="section-header"><p class="eyebrow">Stay Connected</p><h2>Join the Voice of Christian Africa</h2><p class="section-subtitle">Connect your ministry, church, organization, business or individual profile to the VOCA network.</p></div>
                <div class="website-hero-actions"><RouterLink class="button" :to="voca.registrationUrl">Register</RouterLink><a class="button outline" :href="`mailto:${voca.email}`">{{ voca.email }}</a></div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import EthneIntro from '../components/EthneIntro.vue';
import MobileHome from '../components/MobileHome.vue';
import OrganisationHome from '../components/OrganisationHome.vue';
import { useTenant } from '../composables/useTenant';
import { useMobileLayout } from '../composables/useMobileLayout';
import { RouterLink } from 'vue-router';
import { voca, pillars, platforms, pathways, communityImages } from '../data/voca';

const { isMobile } = useMobileLayout();
const { tenant } = useTenant();
const heroImageUrl = communityImages.community.src;
const fallbackMinistryImageUrl = communityImages.leaders.src;

const publicMinistries = ref([]);
const directoryLoading = ref(true);
const publicStreams = computed(() => publicMinistries.value.filter((ministry) => (
    ministry.live?.embed_url || ministry.live?.watch_url
)));

const loadPublicMinistries = async () => {
    directoryLoading.value = true;

    try {
        const { data } = await window.axios.get('/api/public/ministry-directory');
        publicMinistries.value = data.data || [];
    } catch (error) {
        publicMinistries.value = [];
    } finally {
        directoryLoading.value = false;
    }
};

const ministryCardStyle = (ministry) => ({
    '--ministry-primary': ministry.primary_colour || '#bd1622',
    '--ministry-secondary': ministry.secondary_colour || '#111827',
});

const handleLiveClick = (event, ministry) => {
    if (!ministry.live.watch_url) {
        event.preventDefault();
    }
};

onMounted(() => { if (!isMobile.value) loadPublicMinistries(); });
// Fetch the website directory if a browser is resized to the desktop layout.
watch(isMobile, (mobile) => { if (!mobile && directoryLoading.value) loadPublicMinistries(); });
</script>
