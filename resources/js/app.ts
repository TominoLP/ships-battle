import '../css/app.css';
import '@fortawesome/fontawesome-free/css/all.min.css';
import { createApp } from 'vue';
import Game from './pages/Game.vue';
import { i18n, preloadLocale } from '@/src/i18n';

const app = createApp(Game);

app.use(i18n);
preloadLocale().finally(() => app.mount('#app'));
