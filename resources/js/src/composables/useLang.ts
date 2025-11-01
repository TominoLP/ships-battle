import { useI18n } from 'vue-i18n';
import { setLocale, SUPPORTED, type Locale } from '@/src/i18n';

export function useLang() {
	const { t, locale, n, d } = useI18n();
	return {
		t, n, d,
		locale,
		supported: SUPPORTED,
		setLocale: (l: Locale) => setLocale(l)
	};
}
