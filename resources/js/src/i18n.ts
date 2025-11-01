import { createI18n } from 'vue-i18n';

export const SUPPORTED = ['en','de'] as const;
export type Locale = typeof SUPPORTED[number];

function detect(): Locale {
	const saved = localStorage.getItem('locale') as Locale | null;
	if (saved && SUPPORTED.includes(saved)) return saved;
	const nav = navigator.language?.slice(0,2) as Locale;
	return SUPPORTED.includes(nav) ? nav : 'de';
}

export const i18n = createI18n({
	legacy: false,
	locale: detect(),
	fallbackLocale: 'en',
	messages: {} 
});

export async function setLocale(locale: Locale) {
	if (!SUPPORTED.includes(locale)) return;
	if (!i18n.global.availableLocales.includes(locale)) {
		const msgs = await import(`./locales/${locale}.json`);
		i18n.global.setLocaleMessage(locale, msgs.default);
	}
	i18n.global.locale.value = locale;
	document.documentElement.lang = locale;
	localStorage.setItem('locale', locale);
}

export async function preloadLocale() {
	await setLocale(i18n.global.locale.value as Locale);
}
