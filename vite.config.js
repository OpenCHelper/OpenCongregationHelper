import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    server: { 
        hmr: {
            host: 'localhost',
        },
        // Workaround for Windows WSL2 (https://vite.dev/config/server-options#server-watch)
        watch: {
            usePolling: true,
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
