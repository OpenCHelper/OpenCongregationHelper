import { defineConfig } from 'vite'
import laravel, { refreshPaths } from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'

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
            input: [
                'resources/css/app.css',
                'resources/css/guest.css',
                'resources/js/app.js',
                'resources/js/documentation.js',
                'resources/css/filament/app/theme.css',
                'resources/css/filament/guest/theme.css',
                'resources/css/filament/knowledge-base/theme.css',
            ],
            refresh: [
                ...refreshPaths,
                'app/Filament/**',
                'app/Forms/Components/**',
                'app/Livewire/**',
                'app/Infolists/Components/**',
                'app/Providers/Filament/**',
                'app/Tables/Columns/**',
                'docs/user-documentation/**'
            ],
        }),
        tailwindcss(),
    ],
})
