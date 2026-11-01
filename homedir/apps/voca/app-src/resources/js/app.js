import './bootstrap';
import '../css/app.css';

import { createApp } from 'vue';
import { registerSW } from 'virtual:pwa-register';
import router from './router';
import App from './App.vue';
import { useAuth } from './composables/useAuth';
import { useTenant } from './composables/useTenant';
import { initializeNativeApp } from './composables/useNative';

registerSW({ immediate: true });

Promise.allSettled([useTenant().load(), useAuth().load()]).finally(() => {
    const app = createApp(App).use(router);
    initializeNativeApp(router).catch(() => {});
    app.mount('#app');
});
