import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    build: {
        emptyOutDir: true,
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            publicDirectory: '..',
            refresh: true,
        }),
        vue(),
        tailwindcss(),
        {
            name: 'delegate-offline-shell',
            generateBundle(_options, bundle) {
                const entry = Object.values(bundle).find(item => item.type === 'chunk' && item.isEntry && item.facadeModuleId?.endsWith('/resources/js/app.js'));
                if (!entry) return;
                const css = Object.values(bundle).filter(item => item.type === 'asset' && item.fileName.endsWith('.css'));
                this.emitFile({ type: 'asset', fileName: 'movement-shell.html', source: `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>VOCA Delegate Movements</title>${css.map(item => `<link rel="stylesheet" href="/build/${item.fileName}">`).join('')}</head><body><div id="app"></div><script type="module" src="/build/${entry.fileName}"></script></body></html>` });
            },
        },
        VitePWA({
            base: '/',
            outDir: '..',
            registerType: 'autoUpdate',
            includeAssets: ['favicon.png', 'apple-touch-icon.png'],
            workbox: {
                navigateFallback: '/build/movement-shell.html',
                navigateFallbackAllowlist: [/^\/delegates(?:\/|$)/],
                globPatterns: [
                    'build/movement-shell.html',
                    'build/assets/*.{css,js,png,webp}',
                    'build/manifest.webmanifest',
                    'favicon.png',
                    'apple-touch-icon.png',
                    'pwa-192x192.png',
                    'pwa-512x512.png',
                    'pwa-512x512-maskable.png',
                ],
            },
            manifest: {
                name: 'VOCA — Voice of Christian Africa',
                short_name: 'VOCA',
                description: 'Informing Africa. Inspiring Hope. Influencing Nations. Ministry updates, media, events and community access.',
                scope: '/',
                theme_color: '#0b2b4b',
                background_color: '#f6f8fb',
                display: 'standalone',
                start_url: '/',
                icons: [
                    {
                        src: '/pwa-192x192.png',
                        sizes: '192x192',
                        type: 'image/png',
                    },
                    {
                        src: '/pwa-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                    },
                    {
                        src: '/pwa-512x512-maskable.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
            },
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
