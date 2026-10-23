<template>
    <main class="marketplace">
        <section class="marketplace-hero">
            <div class="container marketplace-hero-inner">
                <div>
                    <p class="eyebrow">Voca Commerce</p>
                    <h1>Ministry marketplace</h1>
                    <p>Browse products from every ministry connected to the Voca platform.</p>
                </div>
                <a class="button outline" :href="nativeShopUrl">Open Aimeos shop</a>
            </div>
        </section>

        <section class="section">
            <div class="container">
                <div class="marketplace-toolbar">
                    <label>
                        Search
                        <input v-model.trim="search" type="search" placeholder="Find products, codes, or ministry items" />
                    </label>
                    <label>
                        Ministry
                        <select v-model="ministry">
                            <option value="">All ministries</option>
                            <option v-for="item in ministries" :key="item.site" :value="item.site">
                                {{ item.name }}
                            </option>
                        </select>
                    </label>
                </div>

                <div v-if="status && !status.marketplace_extension" class="marketplace-note">
                    <strong>Marketplace bridge active</strong>
                    <span>Products are aggregated from ministry Aimeos sites and open in the owning ministry shop.</span>
                </div>

                <p v-if="error" class="builder-error" role="alert">{{ error }}</p>
                <div v-if="loading" class="schedules-loading">Loading marketplace...</div>

                <div v-else-if="!products.length" class="schedules-empty marketplace-empty">
                    No ministry products are published yet.
                </div>

                <div v-else class="marketplace-grid">
                    <article v-for="product in products" :key="`${product.site}-${product.id}`" class="marketplace-card">
                        <a class="marketplace-image" :href="product.shop_url" :aria-label="`Open ${product.title}`">
                            <img v-if="product.image_url" :src="product.image_url" :alt="product.title" loading="lazy" />
                            <span v-else>{{ product.ministry.name }}</span>
                        </a>
                        <div class="marketplace-card-body">
                            <p class="portal-tag">{{ product.ministry.name }}</p>
                            <h2>{{ product.title }}</h2>
                            <p v-if="product.summary" class="marketplace-summary">{{ product.summary }}</p>
                            <div class="marketplace-card-footer">
                                <strong>{{ formatPrice(product.price) }}</strong>
                                <a class="button" :href="product.shop_url">Open in ministry shop</a>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </section>
    </main>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';

const products = ref([]);
const ministries = ref([]);
const status = ref(null);
const loading = ref(false);
const error = ref('');
const search = ref('');
const ministry = ref('');
let debounce;

const nativeShopUrl = computed(() => status.value?.native_parent_shop_url || '/commerce/voca/shop/search');

const loadMarketplace = async () => {
    loading.value = true;
    error.value = '';

    try {
        const { data } = await window.axios.get('/api/commerce/marketplace', {
            params: {
                search: search.value || undefined,
                ministry: ministry.value || undefined,
                limit: 72,
            },
        });

        products.value = data.products || [];
        ministries.value = data.ministries || [];
        status.value = data.status || null;
    } catch {
        error.value = 'The marketplace could not be loaded.';
    } finally {
        loading.value = false;
    }
};

const formatPrice = (price) => {
    if (!price) return 'Price in ministry shop';

    return new Intl.NumberFormat('en-ZA', {
        style: 'currency',
        currency: price.currency || 'USD',
    }).format(price.value);
};

watch([search, ministry], () => {
    window.clearTimeout(debounce);
    debounce = window.setTimeout(loadMarketplace, 250);
});

onMounted(loadMarketplace);
</script>
