import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwind from '@tailwindcss/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import { fileURLToPath, URL } from 'node:url';
import fs from 'fs'

const composer = JSON.parse(fs.readFileSync('./composer.json', 'utf-8'))

export default defineConfig({
  define: {
    __APP_VERSION__: JSON.stringify(composer.version || 'dev')
  },
  plugins: [
    vue(),
    tailwind(),
    laravel({
      input: ['resources/js/app.ts'],
      refresh: true
    }),
    wayfinder({
      // optional; defaults are fine for your layout
      // path: 'resources/js',
      // withForm: true, // you’re using .form helpers in your generated file
    })
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url))
    }
  }
});
