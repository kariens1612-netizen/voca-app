<template>
    <section class="section">
        <div class="container">
            <div v-if="ministry?.bannerImage" class="ministry-banner">
                <img :src="ministry.bannerImage" :alt="`${ministry.title} banner`" loading="lazy" />
            </div>
            <div class="section-header">
                <p class="eyebrow">Ministry</p>
                <h2>{{ ministry?.title || 'Ministry not found' }}</h2>
                <p v-if="ministry?.alternateTitle" class="ministry-alias">
                    Also known as {{ ministry.alternateTitle }}
                </p>
                <p class="section-subtitle">
                    {{ ministry?.description || 'We could not find this ministry.' }}
                </p>
            </div>

            <section v-if="isBibleCollege" class="bible-study-panel">
                <div class="bible-study-summary">
                    <p class="ministry-card-label">Featured Bible study</p>
                    <h3>{{ bibleStudyCourse.title }}</h3>
                    <p>{{ bibleStudyCourse.subtitle }}</p>
                    <div class="bible-study-stats" aria-label="Course structure">
                        <span>{{ bibleStudyCourse.weeks }} weeks</span>
                        <span>{{ bibleStudyCourse.days }} daily readings</span>
                        <span>{{ bibleStudyCourse.sessions }} live sessions</span>
                    </div>
                    <div class="hero-actions">
                        <a class="button" :href="bibleStudyCourse.url" target="_blank" rel="noreferrer">
                            Open full study
                        </a>
                        <RouterLink class="button ghost" :to="`/brackenfell/ministries/${route.params.slug}/login`">
                            Member login
                        </RouterLink>
                    </div>
                </div>
                <div class="bible-study-frame-shell">
                    <iframe
                        class="bible-study-frame"
                        :src="bibleStudyCourse.url"
                        :title="bibleStudyCourse.title"
                        loading="lazy"
                    ></iframe>
                </div>
            </section>

            <section v-if="ministry?.leaders?.length" class="house-directory">
                <div class="section-header">
                    <p class="eyebrow">Find your community</p>
                    <h2>Houses of Power leaders and locations</h2>
                    <p class="section-subtitle">
                        Houses of Power and House Churches are the same ministry: family-sized communities gathering
                        in homes and online to help people change their lives.
                    </p>
                </div>
                <div class="house-directory-grid">
                    <article v-for="leader in ministry.leaders" :key="`${leader.name}-${leader.location}`" class="house-card card">
                        <img :src="leader.image" :alt="leader.name" loading="lazy" />
                        <div class="house-card-copy">
                            <p class="ministry-card-label">{{ leader.location }}</p>
                            <h3>{{ leader.name }}</h3>
                        </div>
                    </article>
                </div>
                <div class="card house-invitation">
                    <div>
                        <p class="ministry-card-label">Become part</p>
                        <h3>Looking for love, healing and encouragement?</h3>
                        <p>Connect with a House of Power near you or join the online community.</p>
                    </div>
                    <BookingForm :ministry-slug="ministry.slug" />
                </div>
            </section>

            <div v-if="ministry" class="detail-grid">
                <article class="card ministry-story">
                    <p class="ministry-card-label">About this ministry</p>
                    <h3>{{ ministry.title }}</h3>
                    <p class="lead">{{ ministry.description }}</p>
                    <ul class="detail-points">
                        <li>Discover how this team serves the church every week.</li>
                        <li>Use the member portal for schedules, announcements, and updates.</li>
                        <li>Book time or connect with leadership from the same mobile experience.</li>
                    </ul>
                    <div class="hero-actions" style="margin-top: 20px;">
                        <RouterLink class="button ghost" :to="`/brackenfell/ministries/${route.params.slug}/login`">
                            Member login
                        </RouterLink>
                        <RouterLink class="button outline" to="/">Back to home</RouterLink>
                    </div>
                </article>

                <article class="card detail-card">
                    <p class="ministry-card-label">Next step</p>
                    <h3>Ready to get involved?</h3>
                    <p>
                        Join the ministry flow by signing in, sending a booking request, or exploring more of the
                        Brackenfell hub.
                    </p>
                    <div class="hero-tags">
                        <span>Prayer</span>
                        <span>Serving</span>
                        <span>Community</span>
                    </div>
                </article>
            </div>

            <div v-else class="card">
                <p>Please return to the ministries list and try again.</p>
                <div class="hero-actions" style="margin-top: 20px;">
                    <RouterLink class="button" to="/">Back to home</RouterLink>
                </div>
            </div>
            <BookingForm v-if="ministry && !ministry.leaders?.length" :ministry-slug="ministry.slug" />
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { ministries } from '../data/ministries';
import BookingForm from '../components/BookingForm.vue';

const route = useRoute();
const ministry = computed(() => ministries.find((item) => item.slug === route.params.slug));
const isBibleCollege = computed(() => ministry.value?.slug === 'bible-college');

const bibleStudyCourse = {
    title: 'Fanned Into Flame',
    subtitle: 'A 30-day discipleship journey from meeting God to burning for Him, with Scripture, prayer, reflection, application, and teaching videos.',
    weeks: 5,
    days: 30,
    sessions: 4,
    url: '/courses/fanned-into-flame.html',
};
</script>
