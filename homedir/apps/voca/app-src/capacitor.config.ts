import type { CapacitorConfig } from '@capacitor/cli';

const liveReloadUrl = process.env.CAPACITOR_SERVER_URL;

const config: CapacitorConfig = {
    appId: 'za.co.voca.app',
    appName: 'Voca',
    webDir: '../build',
    bundledWebRuntime: false,
    plugins: {
        Preferences: {
            group: 'VocaPreferences',
        },
    },
};

if (liveReloadUrl) {
    config.server = {
        url: liveReloadUrl,
        cleartext: liveReloadUrl.startsWith('http://'),
    };
}

export default config;
