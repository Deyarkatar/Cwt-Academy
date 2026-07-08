import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/spline-app.tsx',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
        react(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    server: {
        // Bind to all interfaces for local-network testing, but tell the browser
        // to use localhost so HMR/client URLs are valid (0.0.0.0 is not a
        // valid destination for browsers).
        host: '0.0.0.0',
        port: 5173,
        origin: 'http://localhost:5173',
        hmr: {
            clientPort: 5173,
        },
        open: 'http://localhost:8000',
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
