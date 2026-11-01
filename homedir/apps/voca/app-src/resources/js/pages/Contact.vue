<template>
    <section class="section contact-page">
        <div class="container contact-panel">
            <p class="eyebrow">Let’s connect</p>
            <h1>Contact {{ name }}</h1>
            <p>Have a question or want to connect with the community? We’d love to hear from you.</p>
            <a v-if="contact.email" class="contact-option" :href="`mailto:${contact.email}`"><strong>Email us</strong><span>{{ contact.email }} ↗</span></a>
            <a v-if="contact.phone" class="contact-option" :href="`tel:${contact.phone}`"><strong>Call us</strong><span>{{ contact.phone }} ↗</span></a>
            <div v-if="contact.address" class="contact-option"><strong>Find us</strong><span>{{ contact.address }}</span></div>
            <p v-if="!contact.email && !contact.phone && !contact.address">Contact details will appear here when published by your organisation.</p>
            <RouterLink class="mobile-text-link" to="/">Back to home <span aria-hidden="true">→</span></RouterLink>
        </div>
    </section>
</template>
<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { useTenant } from '../composables/useTenant';
import { voca } from '../data/voca';
const { name, brand, tenant } = useTenant();
const contact = computed(() => ({ ...(tenant.value?.slug === 'voca' ? { email: voca.email } : {}), ...brand.value.contact }));
</script>
