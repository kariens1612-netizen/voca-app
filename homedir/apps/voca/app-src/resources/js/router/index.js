import { createRouter, createWebHistory } from 'vue-router';
import Home from '../pages/Home.vue';
import International from '../pages/International.vue';
import MinistryDetail from '../pages/MinistryDetail.vue';
import Brackenfell from '../pages/Brackenfell.vue';
import BrackenfellMinistryLogin from '../pages/BrackenfellMinistryLogin.vue';
import BrackenfellMinistryPortal from '../pages/BrackenfellMinistryPortal.vue';
import BrackenfellMinistryAdmin from '../pages/BrackenfellMinistryAdmin.vue';
import BrackenfellRoster from '../pages/BrackenfellRoster.vue';
import BrackenfellGlobalSchedules from '../pages/BrackenfellGlobalSchedules.vue';
import LiveStream from '../pages/LiveStream.vue';
import Inspiration from '../pages/Inspiration.vue';
import InspirationDetail from '../pages/InspirationDetail.vue';
import InspirationAdmin from '../pages/InspirationAdmin.vue';
import PublicEvents from '../pages/PublicEvents.vue';
import Marketplace from '../pages/Marketplace.vue';
import BibleStudy from '../pages/BibleStudy.vue';
import EventTicket from '../pages/EventTicket.vue';
import EventPortal from '../pages/EventPortal.vue';
import TenantAdmin from '../pages/TenantAdmin.vue';
import PlatformAdmin from '../pages/PlatformAdmin.vue';
import PlatformLogin from '../pages/PlatformLogin.vue';
import FinanceAdmin from '../pages/FinanceAdmin.vue';
import { useAuth } from '../composables/useAuth';
import { useTenant } from '../composables/useTenant';

const routes = [
    { path: '/', name: 'home', component: Home },
    { path: '/live', name: 'live-stream', component: LiveStream },
    { path: '/bible-study', name: 'bible-study', component: BibleStudy },
    { path: '/shop', name: 'marketplace', component: Marketplace },
    { path: '/calendar', name: 'public-events', component: PublicEvents },
    { path: '/events/ticket/:token', name: 'event-ticket', component: EventTicket },
    { path: '/events/portal', name: 'event-portal', component: EventPortal, meta: { requiresAuth: true, leaderOnly: true } },
    { path: '/admin', name: 'tenant-admin', component: TenantAdmin, meta: { requiresAuth: true, organisationAdminOnly: true } },
    { path: '/admin/finance', name: 'finance-admin', component: FinanceAdmin, meta: { requiresAuth: true, financeOnly: true } },
    { path: '/platform', name: 'platform-admin', component: PlatformAdmin, meta: { requiresAuth: true, superadminOnly: true } },
    { path: '/platform/login', name: 'platform-login', component: PlatformLogin },
    { path: '/inspiration', name: 'inspiration', component: Inspiration },
    { path: '/inspiration/:slug', name: 'inspiration-detail', component: InspirationDetail },
    { path: '/international', name: 'international', component: International },
    { path: '/ministries/:slug', name: 'ministry-detail', component: MinistryDetail },
    { path: '/login', name: 'login', component: Brackenfell, alias: '/brackenfell' },
    {
        path: '/ministries/:slug/login',
        alias: '/brackenfell/ministries/:slug/login',
        name: 'brackenfell-ministry-login',
        component: BrackenfellMinistryLogin,
    },
    {
        path: '/portal/ministries/:slug',
        alias: '/brackenfell/ministries/:slug',
        name: 'brackenfell-ministry-portal',
        component: BrackenfellMinistryPortal,
        meta: { requiresAuth: true, ministryRole: 'user', entity: 'brackenfell' },
    },
    {
        path: '/admin/ministries/:slug',
        alias: '/brackenfell/ministries/:slug/admin',
        name: 'brackenfell-ministry-admin',
        component: BrackenfellMinistryAdmin,
        meta: { requiresAuth: true, ministryRole: 'admin', entity: 'brackenfell' },
    },
    {
        path: '/admin/ministries/:slug/content',
        alias: '/brackenfell/ministries/:slug/content',
        name: 'inspiration-admin',
        component: InspirationAdmin,
        meta: { requiresAuth: true, ministryRole: 'admin', entity: 'brackenfell' },
    },
    {
        path: '/admin/ministries/:slug/roster',
        alias: '/brackenfell/ministries/:slug/roster',
        name: 'brackenfell-roster',
        component: BrackenfellRoster,
        meta: { requiresAuth: true, ministryRole: 'admin', entity: 'brackenfell' },
    },
    {
        path: '/admin/scheduling',
        alias: '/brackenfell/scheduling',
        name: 'brackenfell-global-schedules',
        component: BrackenfellGlobalSchedules,
        meta: { requiresAuth: true, superadminOnly: true },
    },
    {
        path: '/brackenfell/ministries/:slug/schedules/global',
        redirect: { name: 'brackenfell-global-schedules' },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior() {
        return { top: 0 };
    },
});

router.beforeEach(async (to) => {
    if (!to.meta?.requiresAuth) {
        return true;
    }

    const auth = useAuth();
    await auth.load();

    if (!auth.user.value) {
        if (to.meta?.superadminOnly) {
            return { name: 'platform-login', query: { redirect: to.fullPath } };
        }
        if (!to.params.slug) {
            return { name: 'login', query: { redirect: to.fullPath } };
        }
        return {
            name: 'brackenfell-ministry-login',
            params: { slug: to.params.slug },
            query: { redirect: to.fullPath },
        };
    }

    if (to.meta?.superadminOnly && !auth.user.value?.is_superadmin) {
        return { name: 'platform-login', query: { denied: '1' } };
    }

    if (to.meta?.superadminOnly) {
        return true;
    }

    if (to.meta?.organisationAdminOnly) {
        const tenant = useTenant();
        await tenant.load();
        return tenant.permissions.value.manage_organisation ? true : { name: 'login', query: { denied: '1' } };
    }

    if (to.meta?.financeOnly) {
        const tenant = useTenant();
        await tenant.load();
        return tenant.permissions.value.finance_access ? true : { name: 'login', query: { denied: '1' } };
    }

    if (to.meta?.leaderOnly) {
        const isLeader = auth.memberships.value.some((item) => ['leader', 'admin'].includes(item.role));
        return isLeader || auth.user.value?.is_superadmin ? true : { name: 'login', query: { denied: '1' } };
    }

    if (!auth.hasRole(to.meta.entity, to.params.slug, to.meta.ministryRole)) {
        return {
            name: 'brackenfell-ministry-login',
            params: { slug: to.params.slug },
            query: { denied: '1' },
        };
    }

    return true;
});

export default router;
