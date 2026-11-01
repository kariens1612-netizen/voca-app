<template>
    <section class="section"><div class="container organisation-home">
        <header class="glass-card admin-panel">
            <img v-if="brand.logo_url" :src="brand.logo_url" :alt="name" class="organisation-logo" />
            <p class="eyebrow">{{ name }}</p>
            <h1>{{ brand.tagline || 'Connect. Grow. Belong.' }}</h1>
            <img v-if="brand.thumbnail_url" :src="brand.thumbnail_url" :alt="name" class="organisation-welcome-image" />
            <p>{{ brand.description || 'Welcome to our community.' }}</p>
            <RouterLink class="button" to="/login">Team sign in</RouterLink>
            <RouterLink v-if="permissions.manage_organisation" class="button" to="/admin">Open dashboard</RouterLink>
        </header>
        <section v-if="brand.contact?.email || brand.contact?.phone || brand.contact?.address" class="glass-card admin-panel"><h2>Contact us</h2><p>{{ brand.contact.email }}</p><p>{{ brand.contact.phone }}</p><p>{{ brand.contact.address }}</p><p v-for="(url, label) in brand.social_links" :key="label"><a v-if="safeLink(url)" :href="url" target="_blank" rel="noopener noreferrer">{{ label }}</a></p></section>
        <div class="admin-grid">
            <RouterLink v-for="feature in features.filter(item => moduleEnabled(item.module))" :key="feature.module" :to="feature.to" class="glass-card admin-panel"><h2>{{ feature.title }}</h2><p>{{ feature.description }}</p></RouterLink>
        </div>
    </div></section>
</template>
<script setup>
import { RouterLink } from 'vue-router';
import { useTenant } from '../composables/useTenant';
const { name, brand, moduleEnabled, permissions } = useTenant();
const safeLink = url => typeof url === 'string' && /^https?:\/\//i.test(url);
const features = [
    { module: 'livestreaming', to: '/live', title: 'Watch live', description: 'Join our latest broadcasts.' },
    { module: 'events', to: '/calendar', title: 'Events', description: 'See what is coming up in our community.' },
    { module: 'inspiration', to: '/inspiration', title: 'Inspiration', description: 'Discover messages and encouragement.' },
];
</script>
<style scoped>
.organisation-home { display: grid; gap: 24px; }
.organisation-home h1 { font-size: clamp(2rem, 6vw, 4rem); overflow-wrap: anywhere; }
.organisation-welcome-image { max-width: 100%; max-height: 360px; object-fit: cover; border-radius: 12px; }
.organisation-logo { max-width: 180px; max-height: 100px; object-fit: contain; }
</style>
