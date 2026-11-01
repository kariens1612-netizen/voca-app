<template>
    <div class="container mobile-home voca-mobile-home">
        <section class="mobile-welcome voca-mobile-hero">
            <img class="mobile-hero-photo" :src="communityImages.community.src" :alt="communityImages.community.alt" fetchpriority="high" />
            <div class="mobile-hero-content">
            <p class="eyebrow">Voice of Christian Africa</p>
            <h1>{{ isVoca ? 'One Voice. One Continent. One Purpose.' : tenantName }}</h1>
            <p>{{ voca.tagline }}</p>
            <RouterLink v-if="moduleEnabled('livestreaming')" class="button" to="/live">Open broadcast <span aria-hidden="true">→</span></RouterLink>
            <RouterLink v-if="isVoca" class="mobile-hero-secondary" :to="voca.registrationUrl">Join the VOCA community <span aria-hidden="true">↗</span></RouterLink>
            </div>
        </section>
        <section aria-labelledby="mobile-explore-title">
            <div class="mobile-section-heading"><div><p class="eyebrow">Connect. Grow. Belong.</p><h2 id="mobile-explore-title">Your VOCA, every day</h2></div></div>
            <div class="mobile-shortcuts">
                <RouterLink v-for="item in shortcuts" :key="item.to" :to="item.to" class="mobile-shortcut">
                    <span class="shortcut-symbol" aria-hidden="true">{{ item.icon }}</span>
                    <strong>{{ item.label }}</strong><small>{{ item.description }}</small>
                </RouterLink>
            </div>
        </section>
        <section v-if="user" aria-labelledby="mobile-team-title" translate="no">
            <div class="mobile-section-heading"><div><p class="eyebrow">Signed in as {{ user.name }}</p><h2 id="mobile-team-title">Your team portal</h2></div></div>
            <div class="mobile-shortcuts">
                <RouterLink v-if="permissions.manage_organisation" class="mobile-shortcut" to="/delegates"><span class="shortcut-symbol" aria-hidden="true">▦</span><strong>Delegate Movements</strong><small>Scan badges and track your group</small></RouterLink>
                <RouterLink v-if="permissions.manage_organisation" class="mobile-shortcut" to="/admin"><span class="shortcut-symbol" aria-hidden="true">⚙</span><strong>Administration</strong><small>Manage your organisation and team</small></RouterLink>
                <RouterLink v-if="permissions.platform_admin" class="mobile-shortcut" to="/platform"><span class="shortcut-symbol" aria-hidden="true">◎</span><strong>Platform admin</strong><small>Manage organisations</small></RouterLink>
                <RouterLink v-for="membership in memberships" :key="membership.slug" class="mobile-shortcut" :to="'/portal/ministries/' + membership.slug"><strong>{{ membership.name }}</strong><small>Open ministry portal</small></RouterLink>
            </div>
        </section>
        <EthneIntro v-if="isVoca" />
        <section v-if="isVoca" class="mobile-media voca-blue-section" aria-labelledby="mobile-media-title">
            <div class="mobile-section-heading"><div><p class="eyebrow">Voices that inspire</p><h2 id="mobile-media-title">Discover VOCA</h2></div></div>
            <div class="mobile-media-grid">
                <a v-for="channel in channels" :key="channel.title" :href="channel.url" class="mobile-media-card">
                    <img :src="channel.image.src" :alt="channel.image.alt" loading="lazy" />
                    <div><small>{{ channel.kicker }}</small><strong>{{ channel.title }} <span aria-hidden="true">↗</span></strong></div>
                </a>
            </div>
        </section>
        <section v-if="isVoca" class="mobile-faith-banner">
            <span class="eyebrow">Rooted in faith. United in purpose.</span>
            <p>Africa’s story deserves to be told with excellence.</p>
            <RouterLink to="/about-us">Meet the voice behind the vision <span aria-hidden="true">→</span></RouterLink>
        </section>
        <section class="mobile-account" aria-labelledby="mobile-account-title">
            <p class="eyebrow">There’s a place for you</p><h2 id="mobile-account-title">Be part of the story.</h2>
            <RouterLink v-if="isVoca" class="button" :to="voca.loginUrl">Member login</RouterLink>
            <RouterLink v-if="isVoca" class="button outline" :to="voca.registrationUrl">Register</RouterLink>
            <RouterLink class="mobile-text-link" :to="permissions.manage_organisation ? '/admin' : '/login'">{{ user ? 'Open team portal' : isVoca ? 'Ministry team portal' : 'Sign in to your portal' }} <span aria-hidden="true">→</span></RouterLink>
        </section>
        <RouterLink v-if="isVoca" class="mobile-text-link" to="/about-us">About VOCA <span aria-hidden="true">→</span></RouterLink>
    </div>
</template>

<script setup>
import EthneIntro from './EthneIntro.vue';
import { computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { useTenant } from '../composables/useTenant';
import { useAuth } from '../composables/useAuth';
import { voca, communityImages } from '../data/voca';
const { tenant, isVoca, name: tenantName, moduleEnabled, permissions } = useTenant();
const { user, memberships, load: loadAuth } = useAuth();
onMounted(loadAuth);
const channels = [
    { title: 'VOCA Radio', kicker: 'Listen & be encouraged', url: 'https://voca.africa/radio', image: communityImages.radio },
    { title: 'VOCA TV', kicker: 'Watch & be inspired', url: 'https://voca.africa/tv', image: communityImages.tv },
];
const shortcuts = computed(() => [
    isVoca.value && { to: '/ethne-app', label: 'Ethne Bible App', description: 'Download and share Scripture offline', icon: '✦' },
    isVoca.value && { to: '/affiliates', label: 'Members', description: 'Meet the VOCA network', icon: '◎' },
    isVoca.value && { to: '/directory', label: 'Business directory', description: 'Find a VOCA member business', icon: '⌕' },
    { to: '/bible-study', label: 'Bible study', description: 'Read and grow in faith', icon: '✦' },
    moduleEnabled('events') && { to: '/calendar', label: 'Events', description: 'See what’s coming up', icon: '▦' },
    moduleEnabled('inspiration') && { to: '/inspiration', label: 'Inspiration', description: 'Stories and encouragement', icon: '☀' },
    isVoca.value && { to: '/international', label: 'Fellowship', description: 'Connect with the network', icon: '◎' },
    moduleEnabled('livestreaming') && { to: '/live', label: 'Broadcasts', description: 'Open the live stream', icon: '▷' },
].filter(Boolean));
</script>
