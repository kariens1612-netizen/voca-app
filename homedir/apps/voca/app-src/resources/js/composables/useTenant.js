import { computed, ref } from 'vue';
import { voca } from '../data/voca';

const tenant = ref(null);
const permissions = ref({});
const preferences = ref(null);
const loaded = ref(false);
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
    pending = window.axios.get('/api/bootstrap').then(({ data }) => {
        const organisation = data.organisation;
        if (organisation?.slug === 'voca') {
            organisation.name = voca.name;
            organisation.branding = {
                ...organisation.branding,
                name: voca.name,
                subtitle: voca.subtitle,
                tagline: voca.tagline,
                footer_text: voca.statement,
                logo_url: '/images/voca/africa-logo.png',
                favicon_url: '/images/voca/africa-logo.png',
                contact: { email: voca.email },
            };
        }
        tenant.value = organisation;
        permissions.value = data.permissions || {};
        preferences.value = data.preferences || null;
        applyBranding(organisation);
        loaded.value = true;
        return data;
    }).finally(() => { pending = null; });
    return pending;
};

const moduleEnabled = (name) => Boolean(tenant.value?.modules?.[name]);

export function useTenant() {
    return {
        tenant, permissions, preferences, loaded,
        brand: computed(() => tenant.value?.branding || {}),
        name: computed(() => tenant.value?.branding?.name || tenant.value?.name || 'Organisation portal'),
        load, moduleEnabled,
    };
}
