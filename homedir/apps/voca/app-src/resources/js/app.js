import './bootstrap';
import '../css/app.css';

import { createApp } from 'vue';
import { registerSW } from 'virtual:pwa-register';
import router from './router';
import App from './App.vue';
import { useTenant } from './composables/useTenant';
import { initializeNativeApp } from './composables/useNative';

registerSW({ immediate: true });

useTenant().load().catch(() => {}).finally(() => {
    const app = createApp(App).use(router);
    initializeNativeApp(router).catch(() => {});
    app.mount('#app');
});
