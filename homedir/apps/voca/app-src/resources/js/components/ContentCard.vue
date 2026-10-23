<template>
    <RouterLink :to="`/inspiration/${item.slug}`" :class="['inspiration-card', { featured }]">
        <img v-if="item.media?.cover" :src="item.media.cover.url" :alt="item.title">
        <div v-else class="inspiration-card-placeholder" aria-hidden="true">
            <span>{{ label }}</span>
        </div>
        <div class="inspiration-card-body">
            <span class="content-type">{{ label }}</span>
            <h2>{{ item.title }}</h2>
            <p>{{ item.summary }}</p>
            <div class="content-meta"><span>{{ item.author }}</span><span>{{ item.ministry?.name }}</span></div>
        </div>
    </RouterLink>
</template>
<script setup>
import { computed } from 'vue'; import { RouterLink } from 'vue-router';
const props = defineProps({ item: { type: Object, required: true }, featured: Boolean });
const labels = { scripture: 'Scripture', newsletter: 'Newsletter', prophetic_word: 'Prophetic word', encouragement: 'Encouragement', video: 'Video' };
const label = computed(() => labels[props.item.type] || props.item.type);
</script>
