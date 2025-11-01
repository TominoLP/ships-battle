<script lang="ts" setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useLang } from '@/src/composables/useLang';

const { t } = useLang();

const gameCode = defineModel<string>('gameCode', { required: true });

const mode = ref<'overview' | 'create' | 'join'>(gameCode.value ? 'join' : 'overview');
const makePublic = ref(false);
const DIGIT_COUNT = 6;

const emit = defineEmits<{
	(e: 'create', options?: { public: boolean }): void;
	(e: 'create-bot'): void;
	(
		e: 'join',
		payload: {
			code: string;
			onSuccess: () => void;
			onError: (message: string) => void;
		}
	): void;
}>();

const inputs = ref<(HTMLInputElement | null)[]>(Array(DIGIT_COUNT).fill(null));
const codeDigits = ref<string[]>(Array(DIGIT_COUNT).fill(''));
const syncingFromModel = ref(false);
const joinError = ref<string | null>(null);
const joinLoading = ref(false);

const combinedCode = computed(() => codeDigits.value.join(''));
const canJoin = computed(() => combinedCode.value.length === DIGIT_COUNT);

// Panels are computed so labels react to locale changes
const panels = computed(() => [
	{
		key: 'create-private' as const,
		title: t('create.private.cardTitle'),
		description: t('create.private.cardDesc'),
		icon: 'fa-solid fa-sailboat',
		accent: 'from-emerald-500/20 to-emerald-500/5 border-emerald-500/40 hover:border-emerald-400',
		action: () => selectCreateType(false),
	},
	{
		key: 'create-public' as const,
		title: t('create.public.cardTitle'),
		description: t('create.public.cardDesc'),
		icon: 'fa-solid fa-bullhorn',
		accent: 'from-amber-500/20 to-amber-500/5 border-amber-500/40 hover:border-amber-400',
		action: () => selectCreateType(true),
	},
	{
		key: 'join' as const,
		title: t('join.cardTitle'),
		description: t('join.cardDesc'),
		icon: 'fa-solid fa-key',
		accent: 'from-blue-500/20 to-blue-500/5 border-blue-500/40 hover:border-blue-400',
		action: () => { mode.value = 'join'; },
	},
	{
		key: 'bot' as const,
		title: t('bot.cardTitle'),
		description: t('bot.cardDesc'),
		icon: 'fa-solid fa-robot',
		accent: 'from-indigo-500/20 to-indigo-500/5 border-indigo-500/40 hover:border-indigo-400',
		action: () => emit('create-bot'),
	},
]);

function goBack() {
	mode.value = 'overview';
}

function selectCreateType(publicGame: boolean) {
	makePublic.value = publicGame;
	mode.value = 'create';
}

function confirmCreate() {
	emit('create', { public: makePublic.value });
}

function confirmJoin() {
	if (!canJoin.value || joinLoading.value) return;

	joinLoading.value = true;
	joinError.value = null;

	try {
		emit('join', {
			code: combinedCode.value,
			onSuccess: () => {
				joinLoading.value = false;
				joinError.value = null;
			},
			onError: (message: string) => {
				joinLoading.value = false;
				joinError.value = message || t('errors.invalidCode');
				focusInput(0);
			},
		});
	} catch {
		joinLoading.value = false;
		joinError.value = t('errors.codeInvalidOrMissing');
	}
}

function normaliseCode(value: string | null | undefined): string {
	return (value ?? '')
		.toUpperCase()
		.replace(/[^A-Z0-9]/g, '')
		.slice(0, DIGIT_COUNT);
}

function syncDigitsFromModel(value: string) {
	const next = Array.from({ length: DIGIT_COUNT }, (_, idx) => value[idx] ?? '');
	if (next.every((val, idx) => val === codeDigits.value[idx])) return;
	syncingFromModel.value = true;
	codeDigits.value = next;
	nextTick(() => { syncingFromModel.value = false; });
}

function applyCharacters(startIndex: number, chars: string) {
	if (!chars) return;
	const list = [...codeDigits.value];
	let pointer = startIndex;

	for (const char of chars) {
		if (pointer >= DIGIT_COUNT) break;
		list[pointer] = char;
		pointer++;
	}

	codeDigits.value = list;
	joinError.value = null;
	if (pointer < DIGIT_COUNT) {
		focusInput(pointer);
	} else {
		focusInput(DIGIT_COUNT - 1);
	}
}

function focusInput(index: number) {
	const input = inputs.value[index];
	if (input) {
		input.focus();
		input.select();
	}
}

function registerInput(el: HTMLInputElement | null, index: number) {
	inputs.value[index] = el;
}

function handleInput(index: number, event: Event) {
	const target = event.target as HTMLInputElement;
	const raw = target.value ?? '';
	const filtered = normaliseCode(raw);

	if (filtered.length > 1) {
		applyCharacters(index, filtered);
		return;
	}

	const list = [...codeDigits.value];
	list[index] = filtered;
	codeDigits.value = list;
	joinError.value = null;

	if (filtered && index < DIGIT_COUNT - 1) {
		focusInput(index + 1);
	}
}

function handleKeydown(index: number, event: KeyboardEvent) {
	if (event.key === 'Backspace') {
		if (codeDigits.value[index]) {
			const list = [...codeDigits.value];
			list[index] = '';
			codeDigits.value = list;
			return;
		}
		if (index > 0) {
			event.preventDefault();
			focusInput(index - 1);
			const list = [...codeDigits.value];
			list[index - 1] = '';
			codeDigits.value = list;
		}
	} else if (event.key === 'ArrowLeft' && index > 0) {
		event.preventDefault();
		focusInput(index - 1);
	} else if (event.key === 'ArrowRight' && index < DIGIT_COUNT - 1) {
		event.preventDefault();
		focusInput(index + 1);
	}
}

function handlePaste(index: number, event: ClipboardEvent) {
	const text = event.clipboardData?.getData('text') ?? '';
	const filtered = normaliseCode(text);
	if (!filtered) return;
	event.preventDefault();
	applyCharacters(index, filtered);
}

watch(codeDigits, (digits) => {
	if (syncingFromModel.value) return;
	const joined = digits.join('');
	gameCode.value = joined;
	joinError.value = null;
}, { deep: true });

watch(gameCode, (value) => {
	const normalised = normaliseCode(value);
	syncDigitsFromModel(normalised);
	if (normalised.length === DIGIT_COUNT && mode.value === 'overview') {
		mode.value = 'join';
	}
});

watch(mode, (value) => {
	if (value === 'join') {
		nextTick(() => {
			const firstEmpty = codeDigits.value.findIndex((digit) => !digit);
			const targetIndex = firstEmpty === -1 ? DIGIT_COUNT - 1 : firstEmpty;
			focusInput(targetIndex);
		});
	} else {
		joinError.value = null;
		joinLoading.value = false;
	}
});
</script>

<template>
	<section class="space-y-6">
		<header class="space-y-2">
			<h3 class="text-lg font-semibold text-slate-100">
				{{ t('create.heading') }}
			</h3>
			<p class="text-sm text-slate-400">
				{{ t('create.subheading') }}
			</p>
		</header>

		<div v-if="mode === 'overview'" class="grid gap-4 sm:grid-cols-2">
			<article
				v-for="panel in panels"
				:key="panel.key"
				class="group relative flex h-full flex-col justify-between rounded-xl border border-transparent bg-gradient-to-br p-4 shadow-lg transition duration-200 hover:-translate-y-0.5 hover:shadow-xl"
				:class="panel.accent"
				role="button"
				tabindex="0"
				@click="panel.action()"
				@keyup.enter="panel.action()"
			>
				<div class="flex items-center gap-3 text-slate-200">
          <span class="flex h-11 w-11 items-center justify-center rounded-full bg-slate-900/60 text-xl">
            <i :class="panel.icon" />
          </span>
					<div>
						<h4 class="text-base font-semibold text-slate-100">
							{{ panel.title }}
						</h4>
						<p class="text-xs text-slate-300 opacity-90">
							{{ panel.description }}
						</p>
					</div>
				</div>
				<div class="mt-4 flex items-center justify-between text-xs text-slate-300">
          <span class="inline-flex items-center gap-1 text-emerald-300/80 group-hover:text-emerald-200">
            <i class="fa-solid fa-arrow-right-long" />
            {{ t('common.select') }}
          </span>
				</div>
			</article>
		</div>

		<form
			v-else-if="mode === 'join'"
			class="space-y-4 rounded-xl border border-slate-700/60 bg-slate-900/80 p-5 shadow-lg transition"
			@submit.prevent="canJoin && confirmJoin()"
		>
			<div class="flex items-center justify-between">
				<h4 class="text-base font-semibold text-slate-100">
					{{ t('join.title') }}
				</h4>
				<button
					type="button"
					class="text-xs text-slate-400 hover:text-slate-200"
					@click="goBack"
				>
					<i class="fa-solid fa-arrow-left-long mr-1" />
					{{ t('common.backToSelection') }}
				</button>
			</div>

			<p class="text-sm text-slate-400">
				{{ t('join.instructions') }}
			</p>

			<div class="space-y-2">
				<label class="text-xs uppercase tracking-wide text-slate-400">{{ t('join.codeLabel') }}</label>
				<div class="grid grid-cols-6 gap-2">
					<input
						v-for="(_, index) in DIGIT_COUNT"
						:key="index"
						:ref="el => registerInput(el as HTMLInputElement | null, index)"
						:value="codeDigits[index]"
						maxlength="1"
						autocapitalize="characters"
						autocomplete="one-time-code"
						inputmode="text"
						:class="[
              'h-12 rounded-lg border bg-slate-800 text-center text-lg font-semibold tracking-[0.2em] text-slate-100 shadow-sm transition focus:outline-none focus:ring-2',
              joinError
                ? 'border-rose-500/80 focus:border-rose-400 focus:ring-rose-400/40'
                : 'border-slate-700 focus:border-blue-500 focus:ring-blue-500/40'
            ]"
						@input="handleInput(index, $event)"
						@keydown="handleKeydown(index, $event)"
						@paste="handlePaste(index, $event)"
						@focus="($event.target as HTMLInputElement).select()"
					/>
				</div>
				<transition name="fade">
					<p
						v-if="joinError"
						class="flex items-start gap-2 mt-3 rounded-lg border border-rose-700/60 bg-rose-900/40 px-3 py-2 text text-rose-100"
					>
						<i class="fa-solid fa-circle-exclamation mt-1" />
						<span>{{ joinError }}</span>
					</p>
				</transition>
			</div>

			<button
				:disabled="!canJoin || joinLoading || !!joinError"
				type="submit"
				class="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:bg-slate-700 disabled:text-slate-400"
			>
				<i v-if="joinLoading" class="fa-solid fa-spinner fa-spin" />
				<i v-else class="fa-solid fa-right-to-bracket" />
				<span>{{ joinLoading ? t('join.connecting') : t('join.joinGame') }}</span>
			</button>
		</form>

		<div
			v-else-if="mode === 'create'"
			class="space-y-4 rounded-xl border border-slate-700/60 bg-slate-900/80 p-5 shadow-lg transition"
		>
			<div class="flex items-center justify-between">
				<h4 class="text-base font-semibold text-slate-100">{{ t('create.createTitle') }}</h4>
				<button
					type="button"
					class="text-xs text-slate-400 hover:text-slate-200"
					@click="goBack"
				>
					<i class="fa-solid fa-arrow-left-long mr-1" />
					{{ t('common.backToSelection') }}
				</button>
			</div>

			<div
				:class="makePublic
          ? 'border-amber-500/60 bg-amber-500/10 text-amber-100'
          : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200'"
				class="rounded-lg border p-4 text-sm"
			>
				<p class="font-semibold flex items-center gap-2">
					<i :class="makePublic ? 'fa-solid fa-bullhorn' : 'fa-solid fa-lock'" />
					{{ makePublic ? t('create.public.title') : t('create.private.title') }}
				</p>
				<p class="mt-1 text-xs opacity-90">
					<template v-if="makePublic">
						{{ t('create.public.blurb') }}
					</template>
					<template v-else>
						{{ t('create.private.blurb') }}
					</template>
				</p>
			</div>

			<div class="flex flex-col gap-2 sm:flex-row">
				<button
					type="button"
					class="flex-1 rounded-lg bg-emerald-600 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-500"
					@click="confirmCreate"
				>
					{{ t('actions.startGame') }}
				</button>
				<button
					type="button"
					class="flex-1 rounded-lg border border-slate-700 py-2.5 text-sm font-semibold text-slate-200 transition hover:border-slate-500 hover:bg-slate-800"
					@click="goBack"
				>
					{{ t('actions.cancel') }}
				</button>
			</div>
		</div>
	</section>
</template>
