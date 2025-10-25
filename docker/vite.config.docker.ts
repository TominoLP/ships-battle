import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwind from '@tailwindcss/vite';
import { fileURLToPath, URL } from 'node:url';
import fs from 'fs';

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
    })
    // wayfinder disabled for Docker builds (no PHP available)
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url))
    }
  }
});