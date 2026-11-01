<template>
    <section class="language-bar" translate="no" data-no-translate aria-label="Language selection">
        <div class="container">
            <button type="button" class="language-toggle" :aria-expanded="open" aria-controls="voca-language-panel" @click="toggle">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3c4.4 4.8 4.4 13.2 0 18-4.4-4.8-4.4-13.2 0-18Z" /></svg>
                <span>Languages</span>
            </button>
            <div v-show="open" id="voca-language-panel" class="language-panel" @keydown.esc="open = false">
                <p>Select Language</p>
                <div id="voca-google-translate-element"></div>
                <p v-if="status" role="status" aria-live="polite">{{ status }}</p>
                <button v-if="failed" type="button" class="language-toggle" @click="load">Retry translation</button>
                <small>Google Translate translates page text online. Images and video remain in their original language.</small>
            </div>
        </div>
    </section>
</template>

<script setup>
import { onBeforeUnmount, ref } from 'vue';

// Same Google Translate element and language list used by uicafrica.com.
const includedLanguages = 'en,af,xh,zu,st,tn,nso,ts,sw,am,ha,ig,yo,rw,sn,so,mg,ny,pt,fr,es,ar,de,it,nl,ru,zh-CN,hi,ur,bn,id,tr';
const open = ref(false);
const failed = ref(false);
const status = ref('');
let timeout;
let loading = false;
let disposed = false;
const callback = 'vocaGoogleTranslateInit';
function initialize() {
    if (disposed) return;
    const container = document.getElementById('voca-google-translate-element');
    if (!container || !window.google?.translate?.TranslateElement) return;
    try {
        if (!container.childNodes.length) {
            new window.google.translate.TranslateElement({
                pageLanguage: 'en', includedLanguages,
                layout: window.google.translate.TranslateElement.InlineLayout.SIMPLE,
                autoDisplay: false,
            }, container.id);
        }
        clearTimeout(timeout);
        loading = false;
        failed.value = false;
        status.value = '';
    } catch { failure(); }
}
function failure() {
    if (disposed) return;
    clearTimeout(timeout);
    loading = false;
    failed.value = true;
    status.value = 'Google Translate could not load. Check your internet connection and retry.';
}
function load() {
    if (window.google?.translate?.TranslateElement) { initialize(); return; }
    if (loading) return;
    failed.value = false;
    loading = true;
    status.value = 'Loading Google Translate…';
    document.getElementById('voca-google-translate-script')?.remove();
    window[callback] = initialize;
    const script = document.createElement('script');
    script.id = 'voca-google-translate-script';
    script.src = `https://translate.google.com/translate_a/element.js?cb=${callback}`;
    script.async = true;
    script.onerror = failure;
    timeout = setTimeout(failure, 15000);
    document.body.appendChild(script);
}
function toggle() {
    open.value = !open.value;
    if (open.value) load();
}
onBeforeUnmount(() => {
    disposed = true;
    clearTimeout(timeout);
    if (window[callback] === initialize) delete window[callback];
});
</script>

<style scoped>
.language-bar { position: relative; z-index: 30; padding: 10px 0; border-bottom: 1px solid #334155; }
.language-toggle { display: inline-flex; align-items: center; gap: 8px; padding: 9px 14px; border: 1px solid #64748b; border-radius: 10px; color: #fff; background: #102340; cursor: pointer; }
.language-panel { padding: 16px 0 8px; }
.language-panel p { margin: 0 0 10px; }
.language-panel small { display: block; margin-top: 12px; }
.language-panel :deep(select) { max-width: 100%; }
</style>
