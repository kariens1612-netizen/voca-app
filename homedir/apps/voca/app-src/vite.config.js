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
        VitePWA({
            base: '/',
            outDir: '..',
            registerType: 'autoUpdate',
            includeAssets: ['favicon.png', 'apple-touch-icon.png'],
            workbox: {
                globPatterns: [
                    'build/assets/*.{css,js}',
                    'build/manifest.webmanifest',
                    'favicon.png',
                    'apple-touch-icon.png',
                    'pwa-192x192.png',
                    'pwa-512x512.png',
                    'pwa-512x512-maskable.png',
                ],
            },
            manifest: {
                name: 'Voca Brackenfell',
                short_name: 'Voca',
                description: 'Voca app for Brackenfell and international satellites.',
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
