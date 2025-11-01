<script lang="ts" setup>
import { useLang } from '@/src/composables/useLang';

const { t, locale, setLocale } = useLang();

const emit = defineEmits<{ (e: 'refresh'): void }>();

function setLang(l: 'en' | 'de') {
	if (locale === l) return;
	setLocale(l as any);
	emit('refresh');
}
</script>

<template>
	<div
		class="inline-flex items-center gap-3 rounded-full border border-slate-700 ml-3 bg-slate-900/80 px-4 py-2 shadow-md hover:bg-slate-800/80 focus-within:outline-none focus-within:ring-2 focus-within:ring-blue-500/60"
		role="group"
		aria-label="Language switch"
	>
		<!-- EN -->
		<button
			type="button"
			@click="setLang('en')"
			:aria-pressed="locale === 'en'"
			aria-label="Switch to English"
			class="h-5 w-8 overflow-hidden rounded-sm outline-none ring-offset-1 focus:ring-2 focus:ring-blue-500/60"
		>
			<svg
				viewBox="0 0 60 30"
				class="h-full w-full transition"
				:class="locale === 'en' ? '' : 'grayscale opacity-70'"
				aria-hidden="true"
			>
				<rect width="60" height="30" fill="#012169"/>
				<path d="M0,0 L60,30 M60,0 L0,30" stroke="#fff" stroke-width="6"/>
				<path d="M0,0 L60,30 M60,0 L0,30" stroke="#C8102E" stroke-width="3"/>
				<path d="M30,0 V30 M0,15 H60" stroke="#fff" stroke-width="10"/>
				<path d="M30,0 V30 M0,15 H60" stroke="#C8102E" stroke-width="6"/>
			</svg>
		</button>

		<!-- DE -->
		<button
			type="button"
			@click="setLang('de')"
			:aria-pressed="locale === 'de'"
			aria-label="Auf Deutsch umschalten"
			class="h-5 w-8 overflow-hidden rounded-sm outline-none ring-offset-1 focus:ring-2 focus:ring-blue-500/60"
		>
			<svg
				viewBox="0 0 5 3"
				class="h-full w-full transition"
				:class="locale === 'de' ? '' : 'grayscale opacity-70'"
				aria-hidden="true"
			>
				<rect width="5" height="3" fill="#000"/>
				<rect y="1" width="5" height="1" fill="#DD0000"/>
				<rect y="2" width="5" height="1" fill="#FFCE00"/>
			</svg>
		</button>

		<span class="sr-only">{{ t('lang.label') }}</span>
	</div>
</template>
