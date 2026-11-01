<template>
    <section class="section directory-page">
        <div class="container">
            <div class="directory-heading">
                <p class="eyebrow">VOCA Member Network</p>
                <h1>{{ listing?.name || 'Business Directory' }}</h1>
                <p v-if="listing">{{ listing.category || listing.business_type || 'VOCA member business' }}</p>
                <p v-else>Discover approved businesses in the Voice of Christian Africa network.</p>
            </div>

            <div v-if="!route.params.slug" class="directory-search">
                <label>Search businesses<input v-model.trim="filters.q" type="search" placeholder="Name, category or description" /></label>
                <label>Country<select v-model="filters.country"><option value="">All countries</option><option v-for="value in options.countries" :key="value">{{ value }}</option></select></label>
                <label>City<select v-model="filters.city"><option value="">All cities</option><option v-for="value in options.cities" :key="value">{{ value }}</option></select></label>
                <label>Category<select v-model="filters.category"><option value="">All categories</option><option v-for="value in options.categories" :key="value">{{ value }}</option></select></label>
            </div>

            <p v-if="error" class="directory-notice" role="alert">{{ error }}</p>
            <p v-else-if="loading" class="directory-notice" role="status">Loading directory…</p>
            <p v-else-if="listing" class="directory-notice">{{ listing.description || 'VOCA member business' }}</p>
            <p v-else-if="!businesses.length" class="directory-notice">No approved listings match those filters yet.</p>

            <div v-if="listing" class="directory-detail card">
                <div class="directory-detail-copy">
                    <p v-if="listing.category" class="directory-category">{{ listing.category }}</p>
                    <p v-if="listing.description">{{ listing.description }}</p>
                    <p v-if="listing.address || listing.city || listing.country">{{ [listing.address, listing.suburb, listing.city, listing.country].filter(Boolean).join(', ') }}</p>
                    <p v-if="listing.business_hours">Hours: {{ listing.business_hours }}</p>
                    <a v-if="listing.website" :href="safeWebsite(listing.website)" target="_blank" rel="noopener">Visit website ↗</a>
                    <a v-if="listing.public_email" :href="`mailto:${listing.public_email}`">{{ listing.public_email }}</a>
                    <a v-if="listing.phone" :href="`tel:${listing.phone}`">{{ listing.phone }}</a>
                    <RouterLink class="button outline" to="/directory">Back to directory</RouterLink>
                </div>
                <img v-if="listing.logo_url" :src="listing.logo_url" :alt="`${listing.name} logo`" loading="lazy" />
            </div>

            <div v-else class="directory-grid">
                <RouterLink v-for="business in businesses" :key="business.id" :to="`/directory/${business.slug}`" class="directory-card card">
                    <img v-if="business.logo_url" :src="business.logo_url" :alt="`${business.name} logo`" loading="lazy" />
                    <span v-else class="directory-monogram" aria-hidden="true">{{ business.name.slice(0, 1) }}</span>
                    <div><p class="directory-category">{{ business.category || business.business_type || 'VOCA member business' }}</p><h2>{{ business.name }}</h2><p>{{ [business.city, business.country].filter(Boolean).join(', ') }}</p><span class="directory-card-link">View business <span aria-hidden="true">→</span></span></div>
                </RouterLink>
            </div>
            <button v-if="!listing && pagination.current_page < pagination.last_page && !loading" class="button outline directory-more" @click="loadMore">Show more businesses</button>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
const route = useRoute();
const businesses = ref([]), listing = ref(null), loading = ref(false), error = ref('');
const options = reactive({ categories: [], countries: [], cities: [] });
const pagination = reactive({ current_page: 1, last_page: 1 });
const filters = reactive({ q: '', category: '', country: '', city: '' });
let debounce;
const safeWebsite = (url) => /^https?:\/\//i.test(url) ? url : `https://${url}`;
const load = async (append = false) => {
    loading.value = true; error.value = '';
    try {
        if (route.params.slug) {
            const { data } = await window.axios.get(`/api/voca/directory/${encodeURIComponent(route.params.slug)}`);
            listing.value = data.data;
            businesses.value = [];
        } else {
            listing.value = null;
            const { data } = await window.axios.get('/api/voca/directory', { params: { ...filters, page: append ? pagination.current_page + 1 : 1 } });
            businesses.value = append ? [...businesses.value, ...(data.data || [])] : (data.data || []);
            Object.assign(pagination, { current_page: data.current_page || 1, last_page: data.last_page || 1 });
            Object.assign(options, data.filters || {});
        }
    } catch (e) {
        if (e?.response?.status === 404) error.value = 'This business is not currently listed in the directory.';
        else error.value = 'The VOCA directory is temporarily unavailable. Please try again.';
    } finally { loading.value = false; }
};
const loadMore = () => load(true);
watch(() => route.params.slug, () => load());
watch(filters, () => { window.clearTimeout(debounce); debounce = window.setTimeout(() => load(), 250); });
onMounted(load);
onUnmounted(() => window.clearTimeout(debounce));
</script>
