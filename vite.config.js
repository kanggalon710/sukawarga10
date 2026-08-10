import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        host: true,        // bind IPv4 (0.0.0.0) + IPv6 so 127.0.0.1 & localhost both resolve
        strictPort: true,  // tetap di 5173 (gagal jelas daripada diam-diam pindah port)
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
