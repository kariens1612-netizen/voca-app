<template>
    <main class="inspiration-hub">
        <section class="inspiration-hero">
            <div class="container">
                <p class="portal-tag">Voca Ministries</p>
                <h1>Inspiration for your journey</h1>
                <p>Scripture, prophetic insight and encouragement to strengthen your faith.</p>
            </div>
        </section>
        <section class="section">
            <div class="container">
                <div class="hub-controls">
                    <div class="hub-tabs" role="tablist" aria-label="Filter inspiration">
                        <button v-for="tab in tabs" :key="tab.value" type="button" :class="{ active: type === tab.value }" @click="setType(tab.value)">{{ tab.label }}</button>
                    </div>
                    <input v-model="search" type="search" placeholder="Search inspiration" aria-label="Search inspiration" @input="debouncedLoad">
                </div>
                <p v-if="error" class="builder-error" role="alert">{{ error }}</p>
                <div v-if="featured.length" class="featured-content-grid">
                    <ContentCard v-for="item in featured" :key="item.id" :item="item" featured />
                </div>
                <div class="inspiration-grid">
                    <ContentCard v-for="item in standard" :key="item.id" :item="item" />
                </div>
                <p v-if="!loading && !items.length" class="empty-state">No inspiration has been published in this category yet.</p>
                <button v-if="page < lastPage" class="button ghost load-more" type="button" @click="loadMore">Load more</button>
            </div>
        </section>
    </main>
</template>
<script setup>
import { computed, onMounted, ref } from 'vue';
import ContentCard from '../components/ContentCard.vue';
import { useAuth } from '../composables/useAuth';
const { user, load: loadAuth } = useAuth();
const tabs = [{ value: '', label: 'All' }, { value: 'scripture', label: 'Scriptures' }, { value: 'newsletter', label: 'Newsletters' }, { value: 'prophetic_word', label: 'Prophetic words' }, { value: 'encouragement', label: 'Encouragement' }, { value: 'video', label: 'Videos' }];
const items = ref([]); const type = ref(''); const search = ref(''); const page = ref(1); const lastPage = ref(1); const loading = ref(false); const error = ref(''); let timer;
const featured = computed(() => items.value.filter(item => item.is_featured));
const standard = computed(() => items.value.filter(item => !item.is_featured));
const load = async (append = false) => { loading.value = true; error.value = ''; try { const endpoint = user.value ? '/inspiration/member/content' : '/inspiration/content'; const response = await window.axios.get(endpoint, { params: { type: type.value, search: search.value, page: page.value } }); items.value = append ? [...items.value, ...response.data.data] : response.data.data; lastPage.value = response.data.last_page; } catch { error.value = 'Inspiration could not be loaded.'; } finally { loading.value = false; } };
const setType = value => { type.value = value; page.value = 1; load(); };
const debouncedLoad = () => { clearTimeout(timer); timer = setTimeout(() => { page.value = 1; load(); }, 300); };
const loadMore = () => { page.value++; load(true); };
onMounted(async () => { await loadAuth(); await load(); });
</script>
