import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
    build: {
        // The only chunk over 500 kB is html2pdf (~950 kB), which PrintButton.jsx
        // loads on demand when someone prints; page bundles stay far below this.
        chunkSizeWarningLimit: 1000,
    },
    /* server: {
        host: true,
        port: 5173,
        hmr: {
            host: '192.168.10.101'
        },
    }, */
});
