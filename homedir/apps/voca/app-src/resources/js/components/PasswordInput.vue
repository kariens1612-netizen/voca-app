<template>
    <span class="password-field">
        <input v-bind="$attrs" v-model="value" :type="visible ? 'text' : 'password'" />
        <button type="button" class="password-toggle" :aria-label="visible ? 'Hide password' : 'Show password'" :aria-pressed="visible" @click="visible = !visible">
            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" />
                <circle cx="12" cy="12" r="3" />
                <path v-if="visible" d="m3 3 18 18" />
            </svg>
        </button>
    </span>
</template>

<script setup>
import { ref } from 'vue';
defineOptions({ inheritAttrs: false });
const value = defineModel({ type: String, default: '' });
const visible = ref(false);
</script>

<style scoped>
.password-field { position: relative; display: block; min-width: 0; }
.password-field input { padding-right: 54px; }
.password-toggle { position: absolute; right: 2px; top: 2px; bottom: 2px; width: 44px; display: grid; place-items: center; border: 0; border-radius: 6px; background: white; color: #172033 !important; -webkit-text-fill-color: #172033; cursor: pointer; }
.password-toggle svg { display: block; width: 22px; height: 22px; stroke: #172033; }
.password-toggle:focus-visible { outline: 2px solid #1b4da3; outline-offset: -3px; }
</style>
