import { isNativeApp } from './useNative';

// Both app layouts share account routes and website-backed authentication.
export function useAccountLayout() {
    return isNativeApp() ? 'mobile-app' : 'web-app';
}
