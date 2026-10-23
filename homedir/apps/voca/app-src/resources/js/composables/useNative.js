import { App } from '@capacitor/app';
import { Capacitor } from '@capacitor/core';
import { Camera, CameraResultType, CameraSource } from '@capacitor/camera';
import { Preferences } from '@capacitor/preferences';
import { PushNotifications } from '@capacitor/push-notifications';

export const isNativeApp = () => Capacitor.isNativePlatform();

export const takePhoto = async () => {
    if (!isNativeApp()) {
        return null;
    }

    return Camera.getPhoto({
        quality: 88,
        allowEditing: false,
        resultType: CameraResultType.Uri,
        source: CameraSource.Prompt,
        width: 2048,
    });
};

export const requestPushPermission = async () => {
    if (!isNativeApp()) {
        return { receive: 'denied' };
    }

    let permission = await PushNotifications.checkPermissions();
    if (permission.receive === 'prompt') {
        permission = await PushNotifications.requestPermissions();
    }

    if (permission.receive === 'granted') {
        await PushNotifications.register();
    }

    return permission;
};

export const initializeNativeApp = async (router) => {
    if (!isNativeApp()) {
        return;
    }

    await App.addListener('appUrlOpen', ({ url }) => {
        try {
            const parsed = new URL(url);
            const path = `${parsed.pathname}${parsed.search}${parsed.hash}`;
            if (path) {
                router.push(path);
            }
        } catch {
            // Ignore malformed deep links and keep the current screen open.
        }
    });

    await PushNotifications.addListener('registration', async ({ value }) => {
        await Preferences.set({ key: 'voca_push_token', value });
        window.dispatchEvent(new CustomEvent('voca:push-token', { detail: value }));
    });

    await PushNotifications.addListener('registrationError', ({ error }) => {
        window.dispatchEvent(new CustomEvent('voca:push-error', { detail: error }));
    });
};
