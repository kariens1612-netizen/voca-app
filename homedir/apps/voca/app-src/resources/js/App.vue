<template>
    <div class="app-shell" :class="{ 'mobile-shell': isMobile, 'voca-brand': isVoca }">
        <div v-if="impersonation?.active" class="impersonation-bar">
            <span>You are viewing this portal as <strong>{{ authUser?.name }}</strong>.</span>
            <button type="button" @click="stopImpersonating">Return to superadmin</button>
        </div>
        <div class="shell-glow shell-glow-one"></div>
        <div class="shell-glow shell-glow-two"></div>

        <header class="site-header">
            <div class="header-top">
                <div class="container topbar">
                    <p>{{ brand.tagline || `${tenantName} community portal.` }}</p>
                    <a v-if="contact.email" :href="`mailto:${contact.email}`">{{ contact.email }}</a>
                </div>
            </div>
            <div class="container header-inner">
                <RouterLink to="/" class="logo">
                    <span class="logo-mark">
                        <img v-if="!logoFailed" :src="logoUrl" @error="logoFailed = true" :alt="`${tenantName} logo`" class="logo-mark-image" />
                    <span v-else aria-hidden="true">V</span>
                    </span>
                    <div class="logo-text">
                        <p class="logo-title">{{ tenantName }}</p>
                        <p class="logo-subtitle">{{ brand.subtitle || 'Connected community infrastructure' }}</p>
                    </div>
                </RouterLink>

                <button v-if="isMobile" ref="menuButton" type="button" class="menu-toggle" :aria-expanded="menuOpen" aria-controls="mobile-menu" :aria-label="menuOpen ? 'Close menu' : 'Open menu'" @click="menuOpen = !menuOpen">
                    <span aria-hidden="true">{{ menuOpen ? '✕' : '☰' }}</span><span>Menu</span>
                </button>
                <nav v-if="!isMobile" class="nav" aria-label="Primary navigation">
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
                        <span class="header-chip-label">Broadcasts</span>
                        <strong>Watch live</strong>
                    </RouterLink>
                </div>
            </div>
            <nav v-if="isMobile && menuOpen" id="mobile-menu" class="mobile-menu container" aria-label="Main menu" @keydown.esc.stop="closeMenu" @click="closeMenu">
                <template v-for="item in navItems" :key="item.to">
                    <a v-if="item.external" :href="item.to">{{ item.label }} <span aria-hidden="true">↗</span></a>
                    <RouterLink v-else :to="item.to">{{ item.label }} <span aria-hidden="true">›</span></RouterLink>
                </template>
            </nav>
        </header>

        <LanguageSelector />

        <main class="main">
            <div v-if="connectionError" class="container" role="alert" style="padding-top:16px;padding-bottom:16px">
                <p>{{ connectionError }}</p><button class="button ghost" type="button" @click="retryConnection">Retry connection</button>
            </div>
            <RouterView />
        </main>

        <footer class="site-footer">
            <div class="container footer-inner">
                <div>
                    <p class="footer-title">{{ tenantName }}</p>
                    <p class="footer-subtitle">{{ contact.address || brand.footer_text || 'Connected community' }}</p>
                    <p class="footer-tech">{{ isVoca ? 'Informing, inspiring and influencing Africa through biblical truth and stories of hope.' : 'Updates, events and community connections.' }}</p>
                </div>
                <div class="footer-links">
                    <a v-if="contact.email" :href="`mailto:${contact.email}`">{{ contact.email }}</a>
                    <a v-if="contact.phone" :href="`tel:${contact.phone}`">{{ contact.phone }}</a>
                </div>
            </div>
        </footer>
        <nav v-if="isMobile" class="app-bottom-nav" aria-label="Quick navigation">
            <RouterLink v-for="item in bottomItems" :key="item.to" :to="item.to" :class="{ active: route.path === item.to }" @click="menuOpen = false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path :d="item.icon" /></svg>
                <span>{{ item.label }}</span>
            </RouterLink>
            <button type="button" :class="{ active: menuOpen }" :aria-expanded="menuOpen" aria-controls="mobile-menu" @click="toggleMenu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                <span>Menu</span>
            </button>
        </nav>


    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import LanguageSelector from './components/LanguageSelector.vue';
import { useMobileLayout } from './composables/useMobileLayout';
import { RouterLink, RouterView, useRoute } from 'vue-router';
import { voca } from './data/voca';
import { useTenant } from './composables/useTenant';
import { useAuth } from './composables/useAuth';

const route = useRoute();
const { isMobile } = useMobileLayout();
const menuOpen = ref(false);
const menuButton = ref(null);
const logoFailed = ref(false);
const closeMenu = () => { menuOpen.value = false; menuButton.value?.focus(); };
watch(() => route.fullPath, () => { menuOpen.value = false; });
watch(isMobile, () => { menuOpen.value = false; });
const { user: authUser, impersonation, load: loadAuth } = useAuth();
const { tenant, isVoca, brand, name: tenantName, permissions, moduleEnabled, loadError: connectionError, load: loadTenant } = useTenant();
const retryConnection = async () => { try { await loadTenant(true); await loadAuth(); } catch {} };
const logoUrl = computed(() => brand.value.logo_url || '/images/voca/voca-logo.png');
const contact = computed(() => brand.value.contact || {});
const bottomItems = computed(() => [
    { to: '/', label: 'Home', icon: 'M3 10 12 3l9 7v10H15v-7H9v7H3Z' },
    moduleEnabled('events') && { to: '/calendar', label: 'Calendar', icon: 'M5 5h14a2 2 0 0 1 2 2v13H3V7a2 2 0 0 1 2-2ZM7 3v4m10-4v4M3 11h18M7 15h2m6 0h2' },
    { to: '/live', label: 'Live', icon: 'M4 5h16v14H4ZM10 9l6 3-6 3Z' },
    { to: '/contact', label: 'Contact', icon: 'M3 5h18v14H3ZM3 6l9 7 9-7' },
].filter(Boolean));
const toggleMenu = () => {
    menuOpen.value = !menuOpen.value;
    if (menuOpen.value) menuButton.value?.focus();
};
const stopImpersonating = async () => {
    await window.axios.post('/api/platform/impersonation/stop');
    await loadAuth();
    window.location.assign('/platform');
};

const navItems = computed(() => {
    const standard = standardNavItems();
    if (brand.value.navigation?.length) {
        const configured = [{ to: '/', label: 'Home' }, ...(authUser.value ? [{ to: '/inside', label: 'Inside' }] : []), ...brand.value.navigation.filter(item => !['/', '/inside'].includes(item.to))].map(item => {
            if (isVoca.value) {
                const labels = { 'Affiliates': 'Members', 'Affiliate': 'Member', 'Affiliate Login': 'Member Login', 'Affiliate login': 'Member login' };
                item = { ...item, label: labels[item.label] || item.label };
                try {
                    const target = new URL(item.to, window.location.origin);
                    const accountPaths = ['/register', '/login', '/member-login', '/forgot-password', '/member-forgot-password', '/about-us', '/affiliates', '/members'];
                    const path = target.pathname.replace(/\/$/, '');
                    if (['voca.africa', 'www.voca.africa'].includes(target.hostname) && accountPaths.includes(path)) {
                        return { ...item, to: (path === '/members' ? '/affiliates' : path) + target.search + target.hash, external: false };
                    }
                } catch { /* Keep unrelated navigation unchanged. */ }
            }
            return item;
        });
        if (isVoca.value && !configured.some((item) => item.to === '/directory')) {
            configured.push({ to: '/directory', label: 'Business Directory', short: '11', match: '/directory' });
        }
        if (permissions.value.manage_organisation && !configured.some(item => item.to === '/delegates')) configured.push({ to: '/delegates', label: 'Delegate Movements', match: '/delegates' });
        if (isVoca.value) {
            for (const item of standard) {
                if (!configured.some(link => link.to === item.to)) configured.push(item);
            }
        }
        return configured;
    }
    return standard;
});

const standardNavItems = () => [
        { to: '/', label: 'Home', match: '/' },
        authUser.value && { to: '/inside', label: 'Inside', short: '01', match: '/inside' },
        isVoca.value && { to: voca.loginUrl, label: 'Member Login', short: '02' },
        isVoca.value && { to: voca.registrationUrl, label: 'Register', short: '03' },
        isVoca.value && { to: '/about-us', label: 'About Us', short: '02' },
        isVoca.value && { to: '/affiliates', label: 'Members', short: '03' },
        isVoca.value && { to: '/international', label: 'Fellowship', short: '04', match: '/international' },
        { to: '/contact', label: 'Contact Us' },
        isVoca.value && { to: '/directory', label: 'Business Directory', short: '11', match: '/directory' },
        { to: '/brackenfell', label: 'Ministries', match: '/brackenfell' },
        { to: '/bible-study', label: 'Bible Study', short: '02', match: '/bible-study' },
        { to: '/live', label: 'Live', short: '03', match: '/live' },
        { to: '/login', label: isVoca.value ? 'Team Portal' : 'Login', short: '04', match: '/login' },
        moduleEnabled('events') && { to: '/calendar', label: 'Events', short: '05', match: '/calendar' },
        moduleEnabled('inspiration') && { to: '/inspiration', label: 'Inspiration', short: '06', match: '/inspiration' },
        permissions.value.finance_access && moduleEnabled('finance') && { to: '/admin/finance', label: 'Finance', short: '08', match: '/admin/finance' },
        permissions.value.manage_organisation && { to: '/delegates', label: 'Delegate Movements', short: '12', match: '/delegates' },
        permissions.value.manage_organisation && { to: '/admin', label: 'Admin', short: '09', match: '/admin' },
        permissions.value.platform_admin && { to: '/platform', label: 'Platform', short: '10', match: '/platform' },
    ].filter(Boolean);

</script>
