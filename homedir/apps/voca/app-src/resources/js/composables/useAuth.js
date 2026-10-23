import { computed, ref } from 'vue';

const user = ref(null);
const memberships = ref([]);
const loaded = ref(false);
const loading = ref(false);
const impersonation = ref(null);

const normalize = (payload) => {
    user.value = payload?.user || null;
    memberships.value = payload?.memberships || [];
    impersonation.value = payload?.impersonation || null;
    loaded.value = true;
};

const load = async () => {
    if (loading.value) {
        return;
    }

    loading.value = true;
    try {
        const response = await window.axios.get('/auth/me');
        normalize(response.data);
    } catch (error) {
        normalize({ user: null, memberships: [] });
    } finally {
        loading.value = false;
    }
};

const login = async (payload) => {
    const response = await window.axios.post('/auth/login', payload);
    normalize(response.data);
    return response.data;
};

const logout = async () => {
    await window.axios.post('/auth/logout');
    normalize({ user: null, memberships: [] });
};

const hasRole = (entity, slug, role = 'user') => {
    if (user.value?.is_superadmin) {
        return true;
    }

    const membership = memberships.value.find(
        (item) => item.entity === entity && item.slug === slug
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

    return true;
};

export function useAuth() {
    return {
        user,
        memberships,
        loaded,
        loading,
        isSuperadmin: computed(() => Boolean(user.value?.is_superadmin)),
        impersonation,
        load,
        login,
        logout,
        hasRole,
    };
}
