import { computed, ref } from 'vue';
import { voca } from '../data/voca.js';
import { isNativeApp } from './useNative';
import { organisationSlug } from './organisationPath';

const tenant = ref(null);
const permissions = ref({});
const preferences = ref(null);
const loaded = ref(false);
const loadError = ref('');
let pending;

const applyBranding = (organisation) => {
    const brand = organisation?.branding || {};
    const root = document.documentElement;
    if (brand.primary_colour) root.style.setProperty('--tenant-primary', brand.primary_colour);
    if (brand.secondary_colour) root.style.setProperty('--tenant-secondary', brand.secondary_colour);
    if (brand.font_family) root.style.setProperty('--tenant-font', brand.font_family);
    document.title = brand.name || organisation?.name || 'Organisation portal';
    if (brand.favicon_url) {
        let icon = document.querySelector('link[rel="icon"]');
        if (!icon) { icon = document.createElement('link'); icon.rel = 'icon'; document.head.appendChild(icon); }
        icon.href = brand.favicon_url;
    }
};

const load = async (force = false) => {
    if (loaded.value && !force) return tenant.value;
    if (pending && !force) return pending;
    pending = window.axios.get('/api/bootstrap', { timeout: 10000 }).then(({ data }) => {
        if (!data?.organisation?.id || !data.organisation.slug) throw new Error('The app is not connected to the VOCA server.');
        const organisation = data.organisation;
        if (organisation?.slug === 'voca') {
            organisation.name = voca.name;
            organisation.branding = {
                ...organisation.branding,
                name: voca.name,
                subtitle: voca.subtitle,
                tagline: voca.tagline,
                footer_text: voca.statement,
                logo_url: '/images/voca/voca-logo.png',
                favicon_url: '/images/voca/voca-logo.png',
                contact: { email: voca.email },
            };
        }
        tenant.value = organisation;
        permissions.value = data.permissions || {};
        preferences.value = data.preferences || null;
        applyBranding(organisation);
        loaded.value = true;
        loadError.value = '';
        return data;
    }).catch(error => {
        loadError.value = 'Unable to connect to the VOCA server. Team access and admin tools are unavailable until the connection is restored.';
        throw error;
    }).finally(() => { pending = null; });
    return pending;
};

// Keep public VOCA navigation visible if its native bootstrap cannot connect.
// Never synthesize an organisation, membership, or management permission.
const publicVocaFallback = () => !tenant.value && isNativeApp() && !organisationSlug();
const isVoca = computed(() => tenant.value?.slug === 'voca' || publicVocaFallback());
const publicModules = new Set(['livestreaming', 'events', 'inspiration', 'commerce', 'public_ministries']);
const moduleEnabled = (name) => tenant.value
    ? Boolean(tenant.value.modules?.[name])
    : publicVocaFallback() && publicModules.has(name);

export function useTenant() {
    return {
        tenant, permissions, preferences, loaded, loadError, isVoca,
        brand: computed(() => tenant.value?.branding || {}),
        name: computed(() => tenant.value?.branding?.name || tenant.value?.name || (publicVocaFallback() ? voca.name : 'Organisation portal')),
        load, moduleEnabled,
    };
}
