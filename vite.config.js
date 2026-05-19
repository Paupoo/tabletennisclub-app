import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/bar.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
    server: {
        cors: true,
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
        hmr: {
            overlay: false, // Disable the red error overlay
        },
    },
})


// import { defineConfig } from 'vite';
// import laravel from 'laravel-vite-plugin';
// import tailwindcss from '@tailwindcss/vite';

// export default defineConfig({
//     plugins: [
//         laravel({
//             input: [
//                 'resources/css/app.css',
//                 'resources/js/app.js',
//             ],
//             refresh: true,
//         }),
//         tailwindcss(),
//     ],
//     server: {
//         cors: true,
//         watch: {
//             ignored: ['**/storage/framework/views/**'],
//         },
//     },
// });
