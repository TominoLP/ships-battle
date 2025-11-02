<script lang="ts" setup>
import { computed, ref, unref } from 'vue';
import { useLang } from '@/src/composables/useLang';

const { t } = useLang();

const props = defineProps({
	gameCode: { type: [String, Object], required: true },
	isReady: { type: [Boolean, Object], required: true },
	isPublic: { type: [Boolean, Object], default: false }
});
const gameCode = computed(() => unref(props.gameCode) as string);
const isReady = computed(() => unref(props.isReady) as boolean);
const isPublic = computed(() => Boolean(unref(props.isPublic)));

const copiedCode = ref(false);
const copiedLink = ref(false);
const DIGIT_COUNT = 6;

const displayCode = computed(() => {
	const normalized = (gameCode.value ?? '').toUpperCase().replace(/[^A-Z0-9]/g, '');
	const padded = normalized.padEnd(DIGIT_COUNT, '·');
	return padded.slice(0, DIGIT_COUNT);
});

const badgeIcon = computed(() => isPublic.value ? 'fa-solid fa-compass' : 'fa-solid fa-anchor');
const badgeClass = computed(() =>
	isPublic.value
		? 'border-blue-500/40 bg-blue-500/10 text-blue-200'
		: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200'
);
const badgeLabel = computed(() =>
	isPublic.value ? t('lobby.publicMatchBadge') : t('lobby.privateMatchBadge')
);

const shareUrl = computed(() => {
	if (typeof window === 'undefined') return '';
	const url = new URL(window.location.href);
	url.searchParams.set('join', gameCode.value);
	return url.toString();
});

const clipboardAvailable = computed(() => typeof navigator !== 'undefined' && !!navigator.clipboard);
const shareAvailable = computed(() => typeof navigator !== 'undefined' && typeof navigator.share === 'function');

async function copy(value: string, kind: 'code' | 'link') {
	if (!value || !clipboardAvailable.value) return;
	try {
		await navigator.clipboard.writeText(value);
		if (kind === 'code') {
			copiedCode.value = true;
			setTimeout(() => (copiedCode.value = false), 1600);
		} else {
			copiedLink.value = true;
			setTimeout(() => (copiedLink.value = false), 1600);
		}
	} catch {
		// no-op
	}
}

async function copyCode() {
	await copy(gameCode.value, 'code');
}

async function copyLink() {
	await copy(shareUrl.value, 'link');
}

async function share() {
	const url = shareUrl.value;
	if (!shareAvailable.value || !url) {
		await copyLink();
		return;
	}
	try {
		await navigator.share({
			title: t('lobby.shareTitle'),
			text: t('lobby.shareText', { code: gameCode.value }),
			url,
		});
	} catch {
		// fall back to copying if user cancels share dialog
	}
}
</script>

<template>
	<section class="space-y-6">
		<header class="flex flex-col gap-2 text-center">
			<p class="text-xs uppercase tracking-[0.25em] text-slate-500">
				{{ isReady ? t('lobby.waitingReadyLabel') : t('lobby.waitingLabel') }}
			</p>
			<h3 class="text-xl font-semibold text-slate-100">
				{{ isReady ? t('lobby.readyTitle') : t('lobby.inviteTitle') }}
			</h3>
			<p class="text-sm text-slate-400 max-w-xl mx-auto">
				{{ isReady ? t('lobby.readyDescription') : t('lobby.inviteDescription') }}
			</p>
		</header>

		<div>
			<article class="flex flex-col gap-5 rounded-2xl border border-slate-700/70 bg-slate-900/80 p-6 shadow-lg">
				<div class="space-y-3">
					<div class="flex items-start justify-between gap-4">
						<div>
							<p class="text-xs uppercase tracking-widest text-slate-500">
								{{ t('lobby.codeLabel') }}
							</p>
							<h4 class="mt-1 text-2xl font-semibold text-slate-100">
								{{ t('lobby.shareLabel') }}
							</h4>
						</div>
						<span
							:class="[
                'inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wide transition',
                badgeClass
              ]"
						>
							<i :class="badgeIcon" />
							{{ badgeLabel }}
						</span>
					</div>
					<div class="rounded-xl border border-slate-700/70 bg-slate-950/80 px-6 py-5 text-center shadow-inner">
						<p class="font-mono text-3xl font-semibold tracking-[0.6em] text-slate-100">
							{{ displayCode }}
						</p>
					</div>
					<p class="text-xs text-slate-500 text-center sm:text-left">
						{{ t('lobby.copyHint') }}
					</p>
				</div>

				<div class="flex flex-wrap items-center gap-2">
					<button
						type="button"
						class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-400"
						:disabled="!clipboardAvailable"
						@click="copyCode"
					>
						<i :class="copiedCode ? 'fa-solid fa-check' : 'fa-solid fa-copy'" />
						<span>{{ copiedCode ? t('lobby.copiedButtonLabel') : t('lobby.copyButtonLabel') }}</span>
					</button>
					<button
						type="button"
						class="inline-flex items-center gap-2 rounded-lg border border-blue-500/40 px-3.5 py-2 text-sm font-semibold text-blue-200 transition hover:border-blue-400 hover:bg-blue-500/15 disabled:cursor-not-allowed disabled:border-slate-700 disabled:text-slate-500"
						:disabled="!clipboardAvailable && !shareAvailable"
						@click="share"
					>
						<i :class="shareAvailable ? 'fa-solid fa-share-nodes' : (copiedLink ? 'fa-solid fa-check' : 'fa-solid fa-link')" />
						<span>{{ shareAvailable ? t('lobby.shareButtonLabel') : copiedLink ? t('lobby.linkCopied') : t('lobby.copyLinkLabel') }}</span>
					</button>
					<button
						type="button"
						class="inline-flex items-center gap-2 rounded-lg border border-slate-700 px-3.5 py-2 text-sm font-semibold text-slate-200 transition hover:border-slate-500 hover:bg-slate-800"
						@click="copyLink"
						:disabled="!clipboardAvailable"
					>
						<i :class="copiedLink ? 'fa-solid fa-check' : 'fa-solid fa-paperclip'" />
						<span>{{ copiedLink ? t('lobby.linkCopied') : t('lobby.copyLinkLabel') }}</span>
					</button>
				</div>

				<transition name="fade">
					<p
						v-if="copiedCode || copiedLink"
						class="flex items-center gap-2 rounded-lg border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-xs font-medium text-emerald-100"
					>
						<i class="fa-solid fa-circle-check" />
						<span>{{ copiedCode ? t('lobby.codeCopyToast') : t('lobby.linkCopyToast') }}</span>
					</p>
				</transition>
			</article>
		</div>

		<footer class="flex flex-col items-center gap-3 text-center text-sm text-slate-400">
			<div v-if="isReady" class="flex items-center gap-2 text-emerald-200">
				<span class="h-3 w-3 rounded-full bg-emerald-400 animate-pulse" />
				<span>{{ t('lobby.readyFooter') }}</span>
			</div>
			<div v-else class="flex items-center gap-3 text-blue-200">
				<span class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-blue-500/40 bg-blue-500/15">
					<span class="h-6 w-6 animate-spin rounded-full border-2 border-blue-400 border-t-transparent" />
				</span>
				<span>{{ t('lobby.waitingFooter') }}</span>
			</div>
		</footer>
	</section>
</template>
