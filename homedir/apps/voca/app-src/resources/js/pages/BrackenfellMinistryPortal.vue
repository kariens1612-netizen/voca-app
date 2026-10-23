<template>
    <section class="section portal-shell">
        <div class="container portal-detail">
            <div class="portal-detail-hero" :style="portalStyle">
                <div>
                    <p class="portal-tag">Member Portal</p>
                    <h1>{{ ministry?.name || 'Ministry' }}</h1>
                    <p>{{ ministry?.description }}</p>
                </div>
                <div class="portal-user-card">
                    <p class="portal-user-label">Signed in as</p>
                    <p class="portal-user-name">{{ user?.name || 'Member' }}</p>
                    <p class="portal-user-role">{{ roleLabel }}</p>
                    <div class="portal-user-actions">
                        <a
                            v-if="whatsappLink"
                            class="button whatsapp"
                            :href="whatsappLink"
                            target="_blank"
                            rel="noopener"
                        >
                            Chat on WhatsApp
                        </a>
                        <button class="button ghost" type="button" @click="logoutUser">Sign out</button>
                        <RouterLink v-if="canAdmin" class="button" :to="adminUrl">Admin view</RouterLink>
                    </div>
                </div>
            </div>

            <div v-if="ministryDetails.length" class="portal-grid ministry-info-grid">
                <article
                    v-for="detail in ministryDetails"
                    :key="detail.label"
                    class="portal-card ministry-info-card"
                >
                    <p class="portal-tag">{{ detail.label }}</p>
                    <p v-for="line in detail.lines" :key="line">{{ line }}</p>
                </article>
            </div>

            <div class="portal-grid">
                <div class="portal-card portal-action">
                    <h3>Upcoming schedule</h3>
                    <p>View service rosters, arrival times, and your next team assignment.</p>
                    <button class="button ghost" type="button">View schedule</button>
                </div>
                <div class="portal-card portal-action">
                    <h3>Request a change</h3>
                    <p>Need to swap a shift? Send a request to your admin team.</p>
                    <button class="button ghost" type="button">Request swap</button>
                </div>
                <div class="portal-card portal-action">
                    <h3>Training resources</h3>
                    <p>Access checklists, setup guides, and ministry updates.</p>
                    <button class="button ghost" type="button">Open resources</button>
                </div>
            </div>

            <SchedulesManager
                :entity="entity"
                :ministry-slug="route.params.slug"
                :can-assign="canAssignSchedules"
            />

            <FinanceSlipUploader
                v-if="ministry?.id"
                :ministry-id="ministry.id"
            />

            <AnnouncementsManager
                :entity="entity"
                :ministry-slug="route.params.slug"
                :can-manage="canManageAnnouncements"
                :is-superadmin="isSuperadmin"
            />

            <KanbanBoard
                :entity="entity"
                :ministry-slug="route.params.slug"
                :can-manage="canAdmin"
            />
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import AnnouncementsManager from '../components/AnnouncementsManager.vue';
import FinanceSlipUploader from '../components/FinanceSlipUploader.vue';
import KanbanBoard from '../components/KanbanBoard.vue';
import SchedulesManager from '../components/SchedulesManager.vue';
import { useAuth } from '../composables/useAuth';
import { useMinistries } from '../composables/useMinistries';

const entity = 'brackenfell';
const route = useRoute();
const router = useRouter();
const { user, memberships, isSuperadmin, logout, hasRole, load } = useAuth();
const { ministriesByEntity, loadEntity, getMinistry } = useMinistries();

const ministry = computed(() => getMinistry(entity, route.params.slug));

const portalStyle = computed(() => ({
    '--portal-accent': ministry.value?.theme?.accent || '#1d5fa2',
    '--portal-surface': ministry.value?.theme?.surface || '#ffffff',
    '--portal-gradient': ministry.value?.theme?.gradient || 'linear-gradient(135deg, #1d5fa2, #0b2b4b)',
}));

const whatsappLink = computed(() => {
    const raw = ministry.value?.whatsapp_number;
    if (!raw) {
        return null;
    }
    const digits = String(raw).replace(/[^\d]/g, '');
    return digits ? `https://wa.me/${digits}` : null;
});

const membership = computed(() =>
    memberships.value.find((item) => item.entity === entity && item.slug === route.params.slug)
);

const roleLabel = computed(() => {
    if (isSuperadmin.value) {
        return 'Superadmin';
    }
    if (membership.value?.role === 'admin') {
        return 'Admin';
    }
    if (membership.value?.role === 'leader') {
        return 'Team leader';
    }
    return 'Member';
});

const canAdmin = computed(() => hasRole(entity, route.params.slug, 'admin'));
const canManageAnnouncements = computed(() => hasRole(entity, route.params.slug, 'leader'));
const canAssignSchedules = computed(() => hasRole(entity, route.params.slug, 'leader'));
const adminUrl = computed(() => `/brackenfell/ministries/${route.params.slug}/admin`);
const ministryDetails = computed(() => {
    const details = ministry.value?.details || {};
    const sections = [
        ['Leadership', details.leaders],
        ['Gatherings', details.schedule],
        ['What we do', details.activities],
        ['Contact', details.contact],
    ];

    return sections
        .map(([label, value]) => ({
            label,
            lines: Array.isArray(value) ? value.filter(Boolean) : value ? [value] : [],
        }))
        .filter((section) => section.lines.length);
});

const logoutUser = async () => {
    await logout();
    router.push(`/brackenfell/ministries/${route.params.slug}/login`);
};

onMounted(async () => {
    if (!ministriesByEntity.value[entity]) {
        await loadEntity(entity);
    }
    await load();
});
</script>
