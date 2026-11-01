import { revokeMovementAccess } from './delegateMovementStore.js';
import { computed, ref } from 'vue';
import { useTenant } from './useTenant.js';

const user = ref(null);
const organisationRole = ref(null);
const memberships = ref([]);
const loaded = ref(false);
const loading = ref(false);
const impersonation = ref(null);

const normalize = (payload) => {
    user.value = payload?.user || null;
    organisationRole.value = payload?.organisation_role || null;
    memberships.value = payload?.memberships || [];
    impersonation.value = payload?.impersonation || null;
    loaded.value = true;
};

let pendingLoad;
const load = () => {
    if (pendingLoad) return pendingLoad;
    loading.value = true;
    pendingLoad = window.axios.get('/auth/me', { timeout: 10000 })
        .then(({ data }) => normalize(data))
        .catch(() => normalize({ user: null, memberships: [] }))
        .finally(() => { loading.value = false; pendingLoad = null; });
    return pendingLoad;
};

const login = async (payload) => {
    const response = await window.axios.post('/auth/login', payload);
    if (!response.data?.user?.id) throw new Error('Sign-in could not reach the VOCA server. Check the app connection and try again.');
    normalize(response.data);
    await useTenant().load(true);
    return response.data;
};

const memberAuthenticate = async (endpoint, payload) => {
    const { data } = await window.axios.post(endpoint, payload);
    if (!data?.user?.id) throw new Error('Sign-in could not reach the VOCA server. Check the app connection and try again.');
    normalize(data);
    await useTenant().load(true);
    return data;
};
const memberLogin = (payload) => memberAuthenticate('/auth/member-login', payload);
const registerMember = (payload) => memberAuthenticate('/auth/member-register', payload);

const logout = async () => {
    await window.axios.post('/auth/logout');
    revokeMovementAccess();
    normalize({ user: null, memberships: [] });
    await useTenant().load(true);
};

const hasRole = (entity, slug, role = 'user') => {
    if (user.value?.is_superadmin || ['owner', 'admin'].includes(organisationRole.value)) {
        return true;
    }

    const membership = memberships.value.find(
        // Memberships are already scoped to the current organisation by the backend.
        (item) => item.slug === slug
    );

    if (!membership) {
        return false;
    }

    if (role === 'admin') {
        return membership.role === 'admin';
    }

    if (role === 'leader') {
        return membership.role === 'admin' || membership.role === 'leader';
    }

    if (role === 'member') return ['admin', 'leader', 'member'].includes(membership.role);
    return true;
};

export function useAuth() {
    return {
        user,
        organisationRole,
        memberships,
        loaded,
        loading,
        isSuperadmin: computed(() => Boolean(user.value?.is_superadmin)),
        impersonation,
        load,
        login,
        memberLogin,
        registerMember,
        logout,
        hasRole,
    };
}
