import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import fs from 'node:fs';

export default defineConfig({
    server: {
        host: '0.0.0.0',
        https: {
            key: fs.readFileSync('./certs/zeiterfassung.local-key.pem'),
            cert: fs.readFileSync('./certs/zeiterfassung.local.pem'),
        },
        hmr: {
            host: 'zeiterfassung.local',
        },
    },
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
});
