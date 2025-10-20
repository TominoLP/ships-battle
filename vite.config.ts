import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import tailwind from '@tailwindcss/vite'
import { wayfinder } from '@laravel/vite-plugin-wayfinder'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
    plugins: [
        vue(),
        tailwind(),
        laravel({
            input: ['resources/js/app.ts'],
            refresh: true,
        }),
      wayfinder({
        // optional; defaults are fine for your layout
        // path: 'resources/js',
        // withForm: true, // you’re using .form helpers in your generated file
      }),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
})
