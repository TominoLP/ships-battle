<script lang="ts" setup>
import { computed, ref, unref } from 'vue';
import { useLang } from '@/src/composables/useLang';

const { t } = useLang();

const props = defineProps({
	gameCode: { type: [String, Object], required: true },
	isReady: { type: [Boolean, Object], required: true }
});
const gameCode = computed(() => unref(props.gameCode) as string);
const isReady = computed(() => unref(props.isReady) as boolean);

const copied = ref(false);

const copyToClip = async () => {
	try {
		await navigator.clipboard.writeText(gameCode.value);
		copied.value = true;
		// optional: auto-hide after a moment
		setTimeout(() => (copied.value = false), 2000);
	} catch {
		// no-op
	}
};
</script>

<template>
	<div class="space-y-3 text-center">
		<p v-if="!isReady" class="font-medium text-slate-300">
			{{ t('lobby.code') }}:
			<span class="font-mono text-lg text-blue-300">{{ gameCode }}</span>
			<i
				class="fa-solid fa-copy text-blue-300 ml-1 hover:text-blue-800 cursor-pointer"
				@click="copyToClip"
			/>
			<transition name="fade" class="mt-4 mb-4 justify-center">
				<div
					v-if="copied"
					class="text-sm text-rose-100 border border-emerald-400/50 rounded-lg px-4 py-3 flex items-center gap-3 shadow-sm"
				>
          <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-700/60 text-xs font-semibold">
            ✓
          </span>
					<span>{{ t('lobby.copied') }}</span>
				</div>
			</transition>
		</p>

		<p v-if="!isReady" class="text-slate-400">
			{{ t('lobby.copyHint') }}
		</p>

		<p v-else class="flex justify-center items-center gap-2">
			<span class="w-6 h-6 border-2 border-emerald-500 border-t-transparent rounded-full animate-spin"></span>
		</p>
	</div>
</template>
