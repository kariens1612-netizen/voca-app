<template>
    <div class="app-shell">
        <div v-if="impersonation?.active" class="impersonation-bar">
            <span>You are viewing this portal as <strong>{{ authUser?.name }}</strong>.</span>
            <button type="button" @click="stopImpersonating">Return to superadmin</button>
        </div>
        <div class="shell-glow shell-glow-one"></div>
        <div class="shell-glow shell-glow-two"></div>

        <header class="site-header">
            <div class="header-top">
                <div class="container topbar">
                    <p>{{ brand.tagline || `${tenantName} digital command centre.` }}</p>
                    <a v-if="contact.email" :href="`mailto:${contact.email}`">{{ contact.email }}</a>
                </div>
            </div>
            <div class="container header-inner">
                <RouterLink to="/" class="logo">
                    <span class="logo-mark">
                        <img :src="logoUrl" :alt="`${tenantName} logo`" class="logo-mark-image" />
                    </span>
                    <div class="logo-text">
                        <p class="logo-title">{{ tenantName }}</p>
                        <p class="logo-subtitle">{{ brand.subtitle || 'Connected community infrastructure' }}</p>
                    </div>
                </RouterLink>

                <nav class="nav" aria-label="Primary navigation">
                    <template v-for="item in navItems" :key="item.to">
                        <a v-if="item.external" :href="item.to" class="nav-link">{{ item.label }}</a>
                        <RouterLink v-else :to="item.to" class="nav-link">{{ item.label }}</RouterLink>
                    </template>
                </nav>

                <div class="header-actions">
                    <div class="header-meta">
                        <span class="header-meta-dot"></span>
                        <span>Live system</span>
                    </div>
                    <RouterLink to="/live" class="header-chip">
                        <span class="header-chip-label">Now streaming</span>
                        <strong>Join live</strong>
                    </RouterLink>
                </div>
            </div>
        </header>

        <main class="main">
            <RouterView />
        </main>

        <footer class="site-footer">
            <div class="container footer-inner">
                <div>
                    <p class="footer-title">{{ tenantName }}</p>
                    <p class="footer-subtitle">{{ contact.address || brand.footer_text || 'Connected community' }}</p>
                    <p class="footer-tech">Mobile-first operations, content, events, and member coordination.</p>
                </div>
                <div class="footer-links">
                    <a v-if="contact.email" :href="`mailto:${contact.email}`">{{ contact.email }}</a>
                    <a v-if="contact.phone" :href="`tel:${contact.phone}`">{{ contact.phone }}</a>
                </div>
            </div>
        </footer>

        <nav class="mobile-dock" aria-label="Mobile navigation">
            <RouterLink
                v-for="item in mobileRouterNavItems"
                :key="`mobile-${item.to}`"
                :to="item.to"
                class="mobile-dock-link"
                :class="{ active: isActive(item) }"
            >
                <span class="mobile-dock-kicker">{{ item.short }}</span>
                <span>{{ item.label }}</span>
            </RouterLink>
            <a
                v-for="item in mobileExternalNavItems"
                :key="`mobile-${item.to}`"
                :href="item.to"
                class="mobile-dock-link"
            >
                <span class="mobile-dock-kicker">{{ item.short }}</span>
                <span>{{ item.label }}</span>
            </a>
        </nav>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { RouterLink, RouterView, useRoute } from 'vue-router';
import { useTenant } from './composables/useTenant';
import { useAuth } from './composables/useAuth';

const route = useRoute();
const { user: authUser, impersonation, load: loadAuth } = useAuth();
const { tenant, brand, name: tenantName, permissions, moduleEnabled } = useTenant();
const logoUrl = computed(() => brand.value.logo_url || '/images/voca/logo.png');
const contact = computed(() => brand.value.contact || {});
const stopImpersonating = async () => {
    await window.axios.post('/api/platform/impersonation/stop');
    await loadAuth();
    window.location.assign('/platform');
};

const navItems = computed(() => {
    if (brand.value.navigation?.length) return brand.value.navigation;
    return [
        { to: '/', label: 'Home', short: '01', match: '/' },
        { to: '/bible-study', label: 'Bible Study', short: '02', match: '/bible-study' },
        moduleEnabled('livestreaming') && { to: '/live', label: 'Live', short: '03', match: '/live' },
        { to: '/login', label: 'Login', short: '04', match: '/login' },
        moduleEnabled('events') && { to: '/calendar', label: 'Events', short: '05', match: '/calendar' },
        moduleEnabled('inspiration') && { to: '/inspiration', label: 'Inspiration', short: '06', match: '/inspiration' },
        moduleEnabled('commerce') && { to: '/shop', label: 'Shop', short: '07', match: '/shop' },
        permissions.value.finance_access && moduleEnabled('finance') && { to: '/admin/finance', label: 'Finance', short: '08', match: '/admin/finance' },
        permissions.value.manage_organisation && { to: '/admin', label: 'Admin', short: '09', match: '/admin' },
        permissions.value.platform_admin && { to: '/platform', label: 'Platform', short: '10', match: '/platform' },
    ].filter(Boolean);
});

const mobileRouterNavItems = computed(() => navItems.value.slice(0, 5));
const mobileExternalNavItems = computed(() => []);

const isActive = (item) => {
    if (item.match === '/') {
        return route.path === '/';
    }

    if (item.match === '/login') {
        return route.path.startsWith('/login') || route.path.startsWith('/brackenfell');
    }

    return route.path.startsWith(item.match);
};
</script>
