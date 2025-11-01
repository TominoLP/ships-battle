<script lang="ts" setup>
import { computed, unref } from 'vue';
import { useLang } from '@/src/composables/useLang';

const { t } = useLang();

const props = defineProps({
	open: { type: [Boolean, Object], required: true },
	youWon: { type: [Boolean, Object, null], default: null },
	winnerName: { type: [String, Object], default: '' },
	rematchState: { type: [String, Object], default: 'idle' },
	rematchError: { type: [String, Object, null], default: null },
	is_bot_game: { type: [Boolean, Object], default: false }
});
const emit = defineEmits<{
	(e: 'close'): void
	(e: 'rematch'): void
}>();

const open = computed(() => unref(props.open) as boolean);
const youWon = computed(() => unref(props.youWon) as boolean | null);
const winnerName = computed(() => unref(props.winnerName) as string);
const rematchState = computed(() => unref(props.rematchState) as 'idle' | 'waiting' | 'ready');
const rematchError = computed(() => {
	const msg = unref(props.rematchError);
	return typeof msg === 'string' && msg.length > 0 ? msg : null;
});

const isDisabled = computed(() => rematchState.value === 'waiting' || rematchState.value === 'ready');

const buttonLabel = computed(() => {
	if (rematchState.value === 'waiting') return t('gameOver.rematch.waiting');
	if (rematchState.value === 'ready') return t('gameOver.rematch.ready');
	return t('gameOver.rematch.start');
});

const statusMessage = computed(() => {
	if (rematchState.value === 'waiting') return t('gameOver.rematch.statusWaiting');
	if (rematchState.value === 'ready') return t('gameOver.rematch.statusReady');
	return null;
});

function onRematch() {
	if (isDisabled.value) return;
	emit('rematch');
}

function onClose() {
	emit('close');
}
</script>

<template>
	<transition name="fade">
		<div
			v-if="open"
			class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
			@click.self="onClose"
		>
			<div
				class="rounded-2xl shadow-md border w-[90%] max-w-sm p-6 text-center bg-slate-900 text-slate-100 border-slate-700"
				role="dialog"
				aria-modal="true"
			>
				<h3 class="text-2xl font-bold mb-1">
					{{ youWon ? t('gameOver.won') : t('gameOver.lost') }}
				</h3>

				<p class="text-slate-400 mb-5">
					{{ t('gameOver.winnerLabel') }}
					<span class="font-semibold text-slate-100">{{ winnerName || '—' }}</span>
				</p>

				<div class="space-y-3">
					<button
						v-if="!is_bot_game"
						type="button"
						:disabled="isDisabled"
						class="w-full rounded-2xl px-4 py-2 font-semibold shadow-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
						:class="isDisabled ? 'bg-blue-500/70 cursor-wait' : 'bg-blue-600 hover:bg-blue-700'"
						@click="onRematch"
					>
						{{ buttonLabel }}
					</button>

					<button
						type="button"
						class="w-full rounded-2xl px-4 py-2 font-semibold shadow-sm border border-slate-300/40 text-slate-200 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400"
						@click="onClose"
					>
						{{ t('gameOver.backToLobby') }}
					</button>
				</div>

				<p v-if="statusMessage" class="mt-4 text-sm text-slate-400">
					{{ statusMessage }}
				</p>
				<p v-if="rematchError" class="mt-2 text-sm text-rose-400">
					{{ rematchError }}
				</p>
			</div>
		</div>
	</transition>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
