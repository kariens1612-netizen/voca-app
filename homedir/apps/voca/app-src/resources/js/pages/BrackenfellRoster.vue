<template>
    <section class="section portal-shell">
        <div class="container portal-detail">
            <div class="portal-detail-hero admin" :style="portalStyle">
                <div>
                    <p class="portal-tag">Superadmin</p>
                    <h1>Full roster</h1>
                    <p>View every ministry and its team members.</p>
                </div>
                <div class="portal-user-card">
                    <p class="portal-user-label">Signed in as</p>
                    <p class="portal-user-name">{{ user?.name || 'Superadmin' }}</p>
                    <p class="portal-user-role">Superadmin</p>
                    <div class="portal-user-actions">
                        <RouterLink class="button ghost" :to="adminUrl">Back to admin</RouterLink>
                        <button class="button" type="button" @click="logoutUser">Sign out</button>
                    </div>
                </div>
            </div>

            <div class="roster-overview-toolbar">
                <label>
                    Search people
                    <input v-model="search" type="text" placeholder="Search by name or email" />
                </label>
                <label>
                    Filter by role
                    <select v-model="roleFilter">
                        <option value="all">All roles</option>
                        <option v-for="role in roles" :key="role" :value="role">
                            {{ roleLabels[role] || role }}
                        </option>
                    </select>
                </label>
                <button class="button ghost" type="button" @click="loadRoster">
                    Refresh
                </button>
            </div>

            <div v-if="loading" class="roster-loading">Loading roster...</div>
            <div v-else class="roster-overview-grid">
                <div
                    v-for="ministry in filteredMinistries"
                    :key="ministry.id"
                    class="roster-overview-card"
                >
                    <div class="roster-overview-header">
                        <div>
                            <p class="roster-overview-entity">{{ ministry.entity }}</p>
                            <h3>{{ ministry.name }}</h3>
                        </div>
                        <span class="roster-overview-count">
                            {{ ministry.members.length }} member{{ ministry.members.length === 1 ? '' : 's' }}
                        </span>
                    </div>

                    <div v-if="!ministry.members.length" class="roster-empty">
                        No members match the current filters.
                    </div>
                    <div v-else class="roster-overview-list">
                        <div v-for="member in ministry.members" :key="member.id" class="roster-overview-item">
                            <div>
                                <p class="roster-name">{{ member.name }}</p>
                                <p class="roster-email">{{ member.email }}</p>
                            </div>
                            <span class="roster-overview-role">
                                {{ roleLabels[member.role] || member.role }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter, RouterLink } from 'vue-router';
import { useAuth } from '../composables/useAuth';
import { useMinistries } from '../composables/useMinistries';

const entity = 'brackenfell';
const route = useRoute();
const router = useRouter();
const { user, logout, load } = useAuth();
const { ministriesByEntity, loadEntity, getMinistry } = useMinistries();

const ministry = computed(() => getMinistry(entity, route.params.slug));
const portalStyle = computed(() => ({
    '--portal-accent': ministry.value?.theme?.accent || '#1d5fa2',
    '--portal-surface': ministry.value?.theme?.surface || '#ffffff',
    '--portal-gradient': ministry.value?.theme?.gradient || 'linear-gradient(135deg, #1d5fa2, #0b2b4b)',
}));

const roles = ['member', 'leader', 'admin'];
const roleLabels = {
    member: 'Member',
    leader: 'Team leader',
    admin: 'Admin',
};

const loading = ref(false);
const ministries = ref([]);
const search = ref('');
const roleFilter = ref('all');

const adminUrl = computed(() => `/brackenfell/ministries/${route.params.slug}/admin`);

const loadRoster = async () => {
    loading.value = true;
    const { data } = await window.axios.get('/brackenfell/roster');
    ministries.value = data.ministries || [];
    loading.value = false;
};

const filteredMinistries = computed(() => {
    const term = search.value.trim().toLowerCase();
    return ministries.value.map((ministryItem) => {
        const members = ministryItem.members.filter((member) => {
            if (roleFilter.value !== 'all' && member.role !== roleFilter.value) {
                return false;
            }
            if (!term) {
                return true;
            }
            return (
                member.name.toLowerCase().includes(term) ||
                member.email.toLowerCase().includes(term)
            );
        });
        return {
            ...ministryItem,
            members,
        };
    });
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
    await loadRoster();
});
</script>
