import { ref, onMounted, onUnmounted } from 'vue';
import { isNativeApp } from './useNative';

export function useMobileLayout() {
    const query = window.matchMedia('(max-width: 860px)');
    const isMobile = ref(isNativeApp() || query.matches);
    const update = () => { isMobile.value = isNativeApp() || query.matches; };
    onMounted(() => query.addEventListener('change', update));
    onUnmounted(() => query.removeEventListener('change', update));
    return { isMobile };
}
