import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import fs from 'node:fs';

export default defineConfig({
    server: {
        host: '0.0.0.0',
        https: fs.existsSync('./certs/zeiterfassung.local-key.pem') && fs.existsSync('./certs/zeiterfassung.local.pem') ? {
            key: fs.readFileSync('./certs/zeiterfassung.local-key.pem'),
            cert: fs.readFileSync('./certs/zeiterfassung.local.pem'),
        } : undefined,
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
