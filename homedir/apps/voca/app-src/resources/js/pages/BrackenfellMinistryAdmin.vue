<template>
    <section class="section portal-shell">
        <div class="container portal-detail">
            <div class="portal-detail-hero admin" :style="portalStyle">
                <div>
                    <p class="portal-tag">Admin Portal</p>
                    <h1>{{ ministry?.name || 'Ministry' }}</h1>
                    <p>Manage members, schedules, and ministry updates.</p>
                </div>
                <div class="portal-user-card">
                    <p class="portal-user-label">Signed in as</p>
                    <p class="portal-user-name">{{ user?.name || 'Admin' }}</p>
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
                        <RouterLink class="button ghost" :to="memberUrl">Member view</RouterLink>
                        <button class="button" type="button" @click="logoutUser">Sign out</button>
                    </div>
                </div>
            </div>

            <div class="portal-grid">
                <div class="portal-card portal-action">
                    <h3>Content Builder</h3>
                    <p>Publish scriptures, newsletters, prophetic words, encouragement and short videos.</p>
                    <RouterLink class="button ghost" :to="contentBuilderUrl">Manage inspiration</RouterLink>
                </div>
                <div v-if="canManageStreamSettings" class="portal-card portal-action">
                    <h3>Live stream settings</h3>
                    <p>Update the Rumble embed link, public watch link, and live page status.</p>
                    <button class="button ghost" type="button" @click="scrollToStreamSettings">
                        Update stream info
                    </button>
                </div>
                <div class="portal-card portal-action">
                    <h3>Announcements</h3>
                    <p>Send updates and reminders to ministry members.</p>
                    <button class="button ghost" type="button" @click="scrollToAnnouncements">
                        Create update
                    </button>
                </div>
                <div v-if="isSuperadmin" class="portal-card portal-action">
                    <h3>Full roster</h3>
                    <p>See every ministry and its team members.</p>
                    <RouterLink class="button ghost" :to="rosterUrl">View roster</RouterLink>
                </div>
                <div v-if="isSuperadmin" class="portal-card portal-action">
                    <h3>Event scheduling</h3>
                    <p>Create and edit events across every ministry in the separate scheduling area.</p>
                    <RouterLink class="button ghost" :to="globalSchedulesUrl">View schedules</RouterLink>
                </div>
            </div>

            <StreamSettingsManager :can-manage="canManageStreamSettings" />

            <AnnouncementsManager
                :entity="entity"
                :ministry-slug="route.params.slug"
                :can-manage="canManageAnnouncements"
                :is-superadmin="isSuperadmin"
            />

            <RosterManager
                :entity="entity"
                :ministry-slug="route.params.slug"
            />

            <KanbanBoard
                :entity="entity"
                :ministry-slug="route.params.slug"
                :can-manage="true"
            />
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import KanbanBoard from '../components/KanbanBoard.vue';
import AnnouncementsManager from '../components/AnnouncementsManager.vue';
import RosterManager from '../components/RosterManager.vue';
import StreamSettingsManager from '../components/StreamSettingsManager.vue';
import { useAuth } from '../composables/useAuth';
import { useMinistries } from '../composables/useMinistries';

const entity = 'brackenfell';
const route = useRoute();
const router = useRouter();
const { user, memberships, isSuperadmin, logout, load } = useAuth();
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
    return membership.value?.role === 'admin' ? 'Admin' : 'Member';
});

const canManageAnnouncements = computed(() => isSuperadmin.value || membership.value?.role === 'admin');
const canManageStreamSettings = computed(
    () => isSuperadmin.value || (route.params.slug === 'power-media' && membership.value?.role === 'admin')
);

const memberUrl = computed(() => `/brackenfell/ministries/${route.params.slug}`);
const rosterUrl = computed(() => `/brackenfell/ministries/${route.params.slug}/roster`);
const contentBuilderUrl = computed(() => `/brackenfell/ministries/${route.params.slug}/content`);
const globalSchedulesUrl = '/brackenfell/scheduling';

const logoutUser = async () => {
    await logout();
    router.push(`/brackenfell/ministries/${route.params.slug}/login`);
};

const scrollToAnnouncements = () => {
    const section = document.getElementById('announcements');
    if (section) {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};

const scrollToStreamSettings = () => {
    const section = document.getElementById('stream-settings');
    if (section) {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
};

onMounted(async () => {
    if (!ministriesByEntity.value[entity]) {
        await loadEntity(entity);
    }
    await load();
});
</script>
