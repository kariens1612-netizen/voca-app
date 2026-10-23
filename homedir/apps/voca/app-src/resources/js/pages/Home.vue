<template>
    <div class="website-home">
        <section class="website-hero">
            <div class="container website-hero-grid">
                <div class="website-hero-copy">
                    <p class="eyebrow">Voca Ministries</p>
                    <h1>In His Presence Preparing His Bride</h1>
                    <p class="lead">
                        A mobile-first home page inspired directly by the Voca website, built around worship,
                        community, prayer, and kingdom equipping.
                    </p>
                    <p class="website-hero-quote">
                        Experience the warm embrace of Family, Love, and Genuine Acceptance.
                    </p>
                    <div class="website-hero-actions">
                        <RouterLink class="button" to="/brackenfell">Voca Brackenfell</RouterLink>
                        <RouterLink class="button" to="/bible-study">Bible Study</RouterLink>
                        <RouterLink class="button outline" to="/international">International Ministries</RouterLink>
                        <a class="button ghost" href="#what-we-do">What We Do</a>
                    </div>
                    <div class="website-hero-tags">
                        <span>Sunday 09H30</span>
                        <span>1 Beatrix Street</span>
                        <span>Brackenfell Cape Town</span>
                    </div>
                </div>

                <article class="website-hero-visual">
                    <img :src="heroImageUrl" alt="Voca worship gathering" loading="eager" />
                    <div class="website-hero-overlay">
                        <span class="card-label">Welcome Home</span>
                        <h3>Love, healing, encouragement, and the presence of God.</h3>
                        <p>
                            Voca is a place where people are empowered and equipped for kingdom ministry.
                        </p>
                    </div>
                </article>
            </div>
        </section>

        <section class="home-study-tabs">
            <div class="container home-study-tabs-inner">
                <RouterLink class="home-study-tab active" to="/bible-study">
                    <span>Bible Study</span>
                    <strong>Read Scripture</strong>
                </RouterLink>
                <RouterLink class="home-study-tab" to="/ministries/bible-college">
                    <span>Bible College</span>
                    <strong>Fanned Into Flame</strong>
                </RouterLink>
                <a class="home-study-tab" href="/courses/fanned-into-flame.html" target="_blank" rel="noreferrer">
                    <span>30-Day Course</span>
                    <strong>Open Study</strong>
                </a>
            </div>
        </section>

        <section class="section section-tight">
            <div class="container">
                <div class="section-header">
                    <p class="eyebrow">Ministry Family</p>
                    <h2>One home page for every ministry.</h2>
                    <p class="section-subtitle">
                        See who is part of the Voca family, open their live broadcast, and connect with them on
                        their social platforms.
                    </p>
                </div>

                <p v-if="directoryLoading" class="public-directory-empty">Loading ministry family...</p>

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
                                    {{ ministry.live.is_live ? 'Watch live on Rumble' : 'Open Rumble' }}
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

        <section class="section section-tight">
            <div class="container">
                <div class="section-header centered">
                    <p class="eyebrow">Our Home</p>
                    <h2>The same core movement, rebuilt for a cleaner mobile experience.</h2>
                </div>

                <div class="website-pillars">
                    <article class="website-pillar card">
                        <img
                            :src="brackenfellImageUrl"
                            alt="A diverse team representing Voca Brackenfell"
                            loading="lazy"
                        />
                        <div class="website-pillar-body">
                            <p class="card-label">Voca Brackenfell</p>
                            <h3>Preparing the Bride to be empowered and equipped.</h3>
                            <p>
                                Join the local expression of Voca in Brackenfell for worship, ministry, family,
                                and kingdom activation.
                            </p>
                            <RouterLink class="button" to="/brackenfell">Open Brackenfell</RouterLink>
                        </div>
                    </article>

                    <article class="website-pillar card">
                        <img :src="worldMapUrl" alt="Voca International Ministries" loading="lazy" />
                        <div class="website-pillar-body">
                            <p class="card-label">Voca International Ministries</p>
                            <h3>Fresh wind, new wine, and new wineskins for the nations.</h3>
                            <p>
                                There is a fresh wind of the Spirit of God blowing across the nations, reaching all who
                                are thirsty for truth and Spirit.
                            </p>
                            <RouterLink class="button outline" to="/international">Explore International</RouterLink>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="container">
                <div class="website-sunday card">
                    <div class="website-sunday-media">
                        <img :src="sundayServiceImageUrl" alt="Sunday service at Voca" loading="lazy" />
                    </div>
                    <div class="website-sunday-copy">
                        <p class="eyebrow">Sunday Gathering</p>
                        <h2>Join us on Sundays 09H30 at Beatrix Street, Brackenfell Industria, Brackenfell Cape Town</h2>
                        <p>
                            Gather with us for worship, the Word, prayer, healing, and a family-centered church
                            experience.
                        </p>
                        <div class="website-sunday-actions">
                            <RouterLink class="button" to="/live">Watch Live</RouterLink>
                            <a class="button ghost" href="#visit">View location</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section section-tight">
            <div class="container">
                <div class="website-support-grid">
                    <article class="website-support-card card">
                        <div class="website-support-image">
                            <img :src="houseChurchImageUrl" alt="Houses of Power (House Churches)" loading="lazy" />
                        </div>
                        <p class="card-label">Houses of Power · House Churches</p>
                        <h3>Family-sized spaces for discipleship, care, and community.</h3>
                        <p>
                            House churches create room for deeper connection, real support, and kingdom life shared in
                            homes.
                        </p>
                        <RouterLink class="button outline" to="/ministries/house-churches">Find a House of Power</RouterLink>
                    </article>

                    <article class="website-support-card card">
                        <div class="website-support-image">
                            <img :src="prayerImageUrl" alt="Need prayer" loading="lazy" />
                        </div>
                        <p class="card-label">Need Prayer?</p>
                        <h3>We will stand with you in prayer.</h3>
                        <p>
                            If you need love, healing, encouragement, or breakthrough, this community believes in the
                            power of prayer and God’s presence.
                        </p>
                        <RouterLink class="button" to="/ministries/healing-prayer">Request Prayer</RouterLink>
                    </article>
                </div>
            </div>
        </section>

        <section id="what-we-do" class="section">
            <div class="container">
                <div class="section-header">
                    <p class="eyebrow">What We Do</p>
                    <h2>Ministries and expressions from the Voca movement.</h2>
                    <p class="section-subtitle">
                        Explore all the ways Voca serves, equips, gathers, and supports people.
                    </p>
                </div>

                <div class="website-ministry-grid">
                    <RouterLink
                        v-for="ministry in ministries"
                        :key="ministry.slug"
                        class="website-ministry-card card card-link"
                        :to="`/ministries/${ministry.slug}`"
                    >
                        <div v-if="ministry.bannerImage" class="website-ministry-media">
                            <img :src="ministry.bannerImage" :alt="ministry.title" loading="lazy" />
                        </div>
                        <p class="card-label">Ministry</p>
                        <h3>{{ ministry.title }}</h3>
                        <p>{{ ministry.description }}</p>
                        <span class="ministry-card-link">Open details</span>
                    </RouterLink>
                </div>
            </div>
        </section>

        <section id="visit" class="section alt">
            <div class="container">
                <div class="website-visit-grid">
                    <div class="website-visit-copy">
                        <p class="eyebrow">Visit Us</p>
                        <h2>Find Voca in Brackenfell.</h2>
                        <p class="section-subtitle">
                            1 Beatrix Street, Brackenfell. If you are looking for a church family rooted in God’s
                            presence, this is where to start.
                        </p>
                        <div class="website-visit-list">
                            <span>Sunday celebration at 09H30</span>
                            <span>Prayer, worship, Word, and ministry</span>
                            <span>Family, love, and genuine acceptance</span>
                        </div>
                    </div>

                    <div class="calendar-frame website-map-frame">
                        <iframe
                            src="https://maps.google.com/maps?q=1%20Beatrix%20Street%2C%20Brackenfell&t=m&z=10&output=embed&iwloc=near"
                            title="1 Beatrix Street, Brackenfell"
                            loading="lazy"
                        ></iframe>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { ministries } from '../data/ministries';

const heroImageUrl = '/images/voca/hero.jpeg';
const brackenfellImageUrl = '/images/voca/leaders.jpg';
const worldMapUrl = '/images/voca/world-map.webp';
const sundayServiceImageUrl = '/images/voca/sunday-service.jpeg';
const houseChurchImageUrl = '/images/voca/power-team.png';
const prayerImageUrl = '/images/voca/intercession.jpg';
const fallbackMinistryImageUrl = '/images/voca/brackenfell-community.jpg';

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

onMounted(loadPublicMinistries);
</script>
