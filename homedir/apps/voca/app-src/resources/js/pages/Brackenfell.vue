<template>
    <section class="section portal-shell">
        <div class="container">
            <div class="portal-hero">
                <div>
                    <p class="eyebrow">Ministry Portals</p>
                    <h1>Ministry login hubs</h1>
                    <p class="lead">
                        Each ministry has a secure portal for members and leaders to access updates, schedules and community tools.
                    </p>
                    <p class="portal-intro-note">
                        Media team members can sign in here to set up and manage the livestream for Rumble through the
                        Power Media portal.
                    </p>
                </div>
                <div class="portal-hero-card">
                    <p class="portal-hero-label">VOCA Network</p>
                    <p class="portal-hero-title">Faith · Truth · Unity · Impact</p>
                    <div class="portal-hero-image">
                        <img :src="directorsImageUrl" alt="Ministry leadership" loading="lazy" />
                    </div>
                    <p>
                        VOCA connects the Church, amplifies credible Christian voices, equips leaders and mobilises faith into action.
                    </p>
                </div>
            </div>

            <div v-if="isLeader" class="portal-grid">
                <RouterLink class="portal-card portal-action" to="/events/portal">
                    <div class="portal-card-header">
                        <span class="portal-tag">Leaders</span>
                        <span class="portal-arrow">→</span>
                    </div>
                    <h3>Event check-in portal</h3>
                    <p>Scan QR tickets, check guests in, and view live attendance numbers.</p>
                </RouterLink>
            </div>

            <div v-if="isSuperadmin" class="portal-grid">
                <RouterLink class="portal-card portal-action" to="/brackenfell/scheduling">
                    <div class="portal-card-header">
                        <span class="portal-tag">Superadmin</span>
                        <span class="portal-arrow">→</span>
                    </div>
                    <h3>Event scheduling</h3>
                    <p>Create, edit, and review events across all ministries from one separate workspace.</p>
                </RouterLink>
            </div>

            <div class="portal-grid">
                <RouterLink
                    v-for="ministry in ministries"
                    :key="ministry.slug"
                    class="portal-card"
                    :style="portalStyle(ministry)"
                    :to="`/brackenfell/ministries/${ministry.slug}/login`"
                >
                    <div class="portal-card-header">
                        <span class="portal-tag">{{ ministry.slug === 'power-media' ? 'Stream Setup' : 'Login' }}</span>
                        <span class="portal-arrow">→</span>
                    </div>
                    <h3>{{ ministry.name }}</h3>
                    <p>{{ ministry.description }}</p>
                    <p v-if="ministry.slug === 'power-media'" class="portal-card-note">
                        Sign in here to prepare the live stream and update the Rumble broadcast details.
                    </p>
                </RouterLink>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { useMinistries } from '../composables/useMinistries';
import { useAuth } from '../composables/useAuth';

const entity = 'brackenfell';
const directorsImageUrl = '/images/voca/leaders.jpg';
const { ministriesByEntity, loadEntity } = useMinistries();
const { isSuperadmin, memberships, load: loadAuth } = useAuth();

onMounted(async () => {
    await Promise.all([loadEntity(entity), loadAuth()]);
});

const ministries = computed(() => ministriesByEntity.value[entity] || []);
const isLeader = computed(() => isSuperadmin.value || memberships.value.some((item) => ['leader', 'admin'].includes(item.role)));

const portalStyle = (ministry) => ({
    '--portal-accent': ministry.theme?.accent || '#1d5fa2',
    '--portal-surface': ministry.theme?.surface || '#ffffff',
    '--portal-gradient': ministry.theme?.gradient || 'linear-gradient(135deg, #1d5fa2, #0b2b4b)',
});
</script>
