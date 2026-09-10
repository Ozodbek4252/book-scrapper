import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

// Set by the `vite` service in compose.yaml. Inside a container the dev server
// must listen on every interface, tell the browser to reach it on localhost,
// and poll for changes because bind mounts do not deliver file events.
const inDocker = process.env.DOCKER === 'true';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        host: inDocker ? '0.0.0.0' : undefined,
        hmr: inDocker ? { host: 'localhost' } : undefined,
        watch: {
            ignored: ['**/storage/framework/views/**'],
            usePolling: inDocker,
        },
    },
});
