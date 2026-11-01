<template>
    <section class="section"><div class="container setup-owner">
        <p class="eyebrow">Owner welcome guide</p><h1>Build your community’s home</h1>
        <p v-if="error" role="alert" class="status-card">{{ error }}</p>
        <p v-if="loading" role="status">Loading your organization…</p>
        <template v-else-if="ready">
            <p>Step {{ step + 1 }} of {{ steps.length }} · {{ steps[step] }}. Changes go live when you finish. Uploaded images are stored immediately.</p>
            <progress :value="step + 1" :max="steps.length" aria-label="Setup progress"></progress>
            <form ref="form" class="glass-card admin-panel" @submit.prevent="next">
                <div v-if="step === 0">
                    <h2>Tell us about your organization</h2>
                    <label>Organization name<input v-model="name" required maxlength="150" /></label>
                    <label>Tagline<input v-model="brand.tagline" maxlength="255" /></label>
                    <label>Welcome / about text<textarea v-model="brand.description" required maxlength="1000" rows="5"></textarea></label>
                    <label>Timezone<input v-model="settings.timezone" required placeholder="Africa/Johannesburg" /></label>
                </div>
                <div v-if="step === 1">
                    <h2>Make it yours</h2>
                    <label>Upload logo (PNG, JPG or WebP, up to 8 MB)<input type="file" accept="image/png,image/jpeg,image/webp" :disabled="busy" @change="upload($event, 'logo_url')" /></label>
                    <img v-if="brand.logo_url" :src="brand.logo_url" alt="Your uploaded logo" class="setup-logo" />
                    <label>Upload welcome image<input type="file" accept="image/png,image/jpeg,image/webp" :disabled="busy" @change="upload($event, 'thumbnail_url')" /></label>
                    <p v-if="busy" role="status">Uploading image…</p>
                    <p v-if="palette.length">Suggested logo colors. Select a swatch to use it as your primary color.</p>
                    <div class="swatches"><button v-for="color in palette" :key="color" type="button" :aria-label="`Use ${color}`" :style="{ background: color }" @click="brand.primary_colour = color">{{ color }}</button></div>
                    <div class="field-row"><label>Primary color<input v-model="brand.primary_colour" type="color" /></label><label>Secondary color<input v-model="brand.secondary_colour" type="color" /></label></div>
                    <p>Colors are sampled from your logo. You can adjust them for readability.</p>
                </div>
                <div v-if="step === 2">
                    <h2>How can people reach you?</h2>
                    <p>These details will be public.</p>
                    <label>Contact email<input v-model="brand.contact.email" required type="email" maxlength="255" /></label>
                    <label>Telephone<input v-model="brand.contact.phone" type="tel" maxlength="50" /></label>
                    <label>Address<textarea v-model="brand.contact.address" maxlength="500"></textarea></label>
                    <label v-for="network in networks" :key="network">{{ network }} URL<input v-model="brand.social_links[network]" type="url" maxlength="2048" /></label>
                </div>
                <div v-if="step === 3">
                    <h2>Choose your tools and first team</h2>
                    <label v-for="(_, key) in modules" :key="key" class="toggle-row"><input v-model="modules[key]" type="checkbox" />{{ labels[key] || key }}</label>
                    <p>Enabling a tool makes its existing features available. Add broadcasts, events, products and other content through their management screens. Booking and commerce integrations need separate configuration.</p>
                    <label><input v-model="addMinistry" type="checkbox" /> Create a first ministry / department</label>
                    <template v-if="addMinistry"><label>Ministry name<input v-model="ministry.name" required maxlength="150" /></label><label>Ministry address slug<input v-model="ministry.slug" required pattern="[A-Za-z0-9_-]+" maxlength="100" placeholder="worship-team" /></label></template>
                    <p>You can add more teams and invite administrators from your dashboard after setup.</p>
                </div>
                <div v-if="step === 4">
                    <h2>Review your home page</h2>
                    <article class="setup-preview" :style="{ borderColor: brand.primary_colour, backgroundColor: brand.secondary_colour }">
                        <img v-if="brand.logo_url" :src="brand.logo_url" alt="Organization logo" class="setup-logo" />
                        <h3>{{ name }}</h3><h4>{{ brand.tagline }}</h4><p>{{ brand.description }}</p>
                        <img v-if="brand.thumbnail_url" :src="brand.thumbnail_url" alt="Welcome image" class="setup-photo" />
                        <p>{{ brand.contact.email }} · {{ brand.contact.phone }}</p><p>{{ brand.contact.address }}</p>
                    </article>
                    <p>Enabled tools: {{ Object.keys(modules).filter(key => modules[key]).map(key => labels[key] || key).join(', ') || 'None' }}.</p>
                    <p v-if="addMinistry">First team: {{ ministry.name }} ({{ ministry.slug }}).</p>
                    <p>Finish applies the branding to your existing public page and dashboard. Content and invitations can be added next.</p>
                </div>
                <div class="button-row"><button v-if="step" type="button" class="button ghost" :disabled="busy" @click="step--">Back</button><button class="button" :disabled="busy">{{ step === 4 ? 'Finish and open dashboard' : 'Continue' }}</button></div>
            </form>
        </template>
    </div></section>
</template>
<script setup>
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useTenant } from '../composables/useTenant';
const router = useRouter();
const { load, permissions, tenant } = useTenant();
const steps = ['Your organization', 'Branding', 'Contact', 'Features and ministries', 'Review'];
const networks = ['website', 'facebook', 'instagram', 'youtube', 'tiktok'];
const labels = { livestreaming: 'Live broadcasts', events: 'Events and tickets', inspiration: 'Inspiration content', schedules: 'Team schedules', announcements: 'Announcements', kanban: 'Task boards', bookings: 'Bookings', public_ministries: 'Public ministry pages', commerce: 'Marketplace', finance: 'Finance slips' };
const step = ref(0), name = ref(''), error = ref(''), busy = ref(false), loading = ref(true), ready = ref(false), form = ref(null), palette = ref([]), addMinistry = ref(false);
const brand = reactive({ name: '', tagline: '', description: '', logo_url: '', thumbnail_url: '', primary_colour: '#1b4da3', secondary_colour: '#081224', contact: {}, social_links: {} });
const settings = reactive({ timezone: 'Africa/Johannesburg' }), modules = reactive({}), ministry = reactive({ name: '', slug: '' });
onMounted(async () => {
    try {
        await load(true);
        if (!permissions.value.manage_owners) { error.value = 'An organization owner must complete setup.'; return; }
        const { data } = await window.axios.get('/api/admin/settings');
        const org = data.data;
        if (org.settings?.onboarding?.completed_at) { await router.replace('/admin'); return; }
        name.value = org.name;
        Object.assign(brand, org.branding || {}); brand.contact ||= {}; brand.social_links ||= {};
        Object.assign(settings, org.settings || {}); Object.assign(modules, tenant.value.modules || {}, org.modules || {});
        ready.value = true;
    } catch (e) { error.value = e.response?.data?.message || 'Unable to load setup. Refresh to try again.'; }
    finally { loading.value = false; }
});
async function suggestColors(file) {
    const bitmap = await createImageBitmap(file);
    try {
        const canvas = document.createElement('canvas'); canvas.width = canvas.height = 48;
        const ctx = canvas.getContext('2d'); ctx.drawImage(bitmap, 0, 0, 48, 48);
        const pixels = ctx.getImageData(0, 0, 48, 48).data, counts = new Map();
        for (let i = 0; i < pixels.length; i += 4) {
            if (pixels[i + 3] < 180) continue;
            const rgb = [pixels[i], pixels[i + 1], pixels[i + 2]].map(n => Math.min(255, Math.round(n / 32) * 32));
            if (Math.min(...rgb) > 224 || Math.max(...rgb) < 24) continue;
            const hex = '#' + rgb.map(n => n.toString(16).padStart(2, '0')).join('');
            counts.set(hex, (counts.get(hex) || 0) + 1);
        }
        palette.value = [...counts].sort((a, b) => b[1] - a[1]).slice(0, 5).map(([hex]) => hex);
    } finally { bitmap.close(); }
}
async function upload(event, field) {
    const file = event.target.files?.[0]; if (!file) return;
    error.value = '';
    if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 8 * 1024 * 1024) { error.value = 'Choose a PNG, JPG or WebP image up to 8 MB.'; event.target.value = ''; return; }
    busy.value = true;
    try {
        const body = new FormData(); body.append('asset', file);
        const { data } = await window.axios.post('/api/admin/media', body);
        brand[field] = data.data.url;
        if (field === 'logo_url') { try { await suggestColors(file); } catch { palette.value = []; } }
    } catch (e) { error.value = e.response?.data?.message || 'Upload failed. Please try again.'; }
    finally { busy.value = false; event.target.value = ''; }
}
async function next() {
    if (!form.value.reportValidity()) return;
    error.value = '';
    if (step.value < 4) { step.value++; return; }
    busy.value = true;
    try {
        brand.name = name.value;
        await window.axios.post('/api/admin/setup', { name: name.value, branding: brand, settings, modules, ...(addMinistry.value ? { first_ministry: ministry } : {}) });
        await load(true); await router.replace('/admin');
    } catch (e) {
        error.value = Object.values(e.response?.data?.errors || {}).flat().join(' ') || e.response?.data?.message || 'Unable to finish setup. Your entries are still here; please try again.';
    } finally { busy.value = false; }
}
</script>
<style scoped>
.setup-owner { max-width: 850px; } progress { width: 100%; margin-bottom: 24px; } .setup-logo { max-width: 180px; max-height: 110px; object-fit: contain; } .setup-photo { max-width: 100%; max-height: 240px; object-fit: cover; } .setup-preview { padding: 24px; border: 4px solid; border-radius: 16px; color: white; overflow-wrap: anywhere; } .swatches { display: flex; flex-wrap: wrap; gap: 10px; } .swatches button { padding: 14px; border: 2px solid #888; border-radius: 8px; color: white; text-shadow: 0 1px 3px black; cursor: pointer; } .button-row { margin-top: 24px; }
</style>
