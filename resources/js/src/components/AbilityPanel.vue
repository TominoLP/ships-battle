<script setup lang="ts">
import type { AbilityType } from '@/src/composables/useAbilities';
import { useLang } from '@/src/composables/useLang';

interface Props {
	canUsePlane: boolean;
	canUseBomb: boolean;
	canUseSplatter: boolean;
	planeRemaining: number;
	planeTotal: number;
	bombRemaining: number;
	bombTotal: number;
	splatterRemaining: number;
	splatterTotal: number;
	planeExhausted: boolean;
	bombExhausted: boolean;
	splatterExhausted: boolean;
}

const props = defineProps<Props>();
const { t } = useLang();

const emit = defineEmits<{
	startAbility: [type: AbilityType, event: PointerEvent];
	showError: [title: string, message: string];
}>();

function handleAbilityStart(type: AbilityType, ev: PointerEvent) {
	if (type === 'plane' && !props.canUsePlane) {
		emit('showError', t('abilities.errors.planeExhausted.title'), t('abilities.errors.planeExhausted.message'));
		return;
	}
	if (type === 'comb' && !props.canUseBomb) {
		emit('showError', t('abilities.errors.bombLocked.title'), t('abilities.errors.bombLocked.message'));
		return;
	}
	if (type === 'splatter' && !props.canUseSplatter) {
		emit('showError', t('abilities.errors.splatterExhausted.title'), t('abilities.errors.splatterExhausted.message'));
		return;
	}
	emit('startAbility', type, ev);
}
</script>

<template>
	<div class="grid gap-2">
		<!-- PLANE -->
		<button
			class="rounded-lg border px-3 py-2 text-left hover:bg-slate-800 border-slate-700 bg-slate-800/60 disabled:opacity-50"
			:disabled="!canUsePlane"
			:title="t('abilities.plane.title')"
			@pointerdown.prevent="handleAbilityStart('plane', $event)"
		>
			<div class="flex items-start justify-between">
				<div>
					<div class="font-medium text-slate-100">{{ t('abilities.plane.name') }}</div>
					<div class="text-[11px] text-slate-400">{{ t('abilities.plane.subtitle') }}</div>
				</div>
				<span class="flex items-center gap-2">
          <span
						v-if="!planeExhausted"
						class="inline-flex items-center rounded-full border px-2 py-[2px] text-[11px]
                   bg-slate-800/70 text-slate-300 border-slate-600/60"
						:title="t('placing.remainingTotal')"
					>
            {{ planeRemaining }}/{{ planeTotal }}
          </span>
          <span
						v-else
						class="inline-flex items-center gap-1 rounded-full border px-2 py-[2px] text-[11px]
                   bg-emerald-600/15 text-emerald-300 border-emerald-500/40"
					>
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
              <path d="M20 6L9 17l-5-5" />
            </svg>
          </span>
        </span>
			</div>
		</button>

		<!-- BOMB -->
		<button
			class="rounded-lg border px-3 py-2 text-left hover:bg-slate-800 border-slate-700 bg-slate-800/60 disabled:opacity-50"
			:disabled="!canUseBomb || bombExhausted"
			:title="bombExhausted ? t('abilities.bomb.tooltipUsed') : t('abilities.bomb.tooltipReady')"
			@pointerdown.prevent="handleAbilityStart('comb', $event)"
		>
			<div class="flex items-start justify-between">
				<div>
					<div class="font-medium text-slate-100">{{ t('abilities.bomb.name') }}</div>
					<div class="text-[11px] text-slate-400">
						<template v-if="bombExhausted">
							{{ t('abilities.bomb.subtitleUsed') }}
						</template>
						<template v-else>
							{{ t('abilities.bomb.subtitleReady') }}
						</template>
					</div>
				</div>

				<span class="flex items-center gap-2">
          <span
						v-if="bombExhausted"
						class="inline-flex items-center rounded-full border px-2 py-[2px] text-[11px]
                   bg-slate-800/70 text-slate-300 border-slate-600/60"
					>
            {{ t('common.exhausted') }}
          </span>
          <span
						v-else-if="!canUseBomb"
						class="inline-flex items-center rounded-full border px-2 py-[2px] text-[11px]
                   bg-slate-800/70 text-slate-300 border-slate-600/60"
					>
            {{ t('common.locked') }}
          </span>
          <span
						v-else
						class="inline-flex items-center gap-1 rounded-full border px-2 py-[2px] text-[11px]
                   bg-emerald-600/15 text-emerald-300 border-emerald-500/40"
					>
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
              <path d="M20 6L9 17l-5-5" />
            </svg>
            {{ t('actions.ready') }}
          </span>
        </span>
			</div>
		</button>

		<!-- SPLATTER -->
		<button
			class="rounded-lg border px-3 py-2 text-left hover:bg-slate-800 border-slate-700 bg-slate-800/60 disabled:opacity-50"
			:disabled="!canUseSplatter"
			:title="t('abilities.splatter.title')"
			@pointerdown.prevent="handleAbilityStart('splatter', $event)"
		>
			<div class="flex items-start justify-between">
				<div>
					<div class="font-medium text-slate-100">{{ t('abilities.splatter.name') }}</div>
					<div class="text-[11px] text-slate-400">{{ t('abilities.splatter.subtitle') }}</div>
				</div>
				<span class="flex items-center gap-2">
          <span
						v-if="!splatterExhausted"
						class="inline-flex items-center rounded-full border px-2 py-[2px] text-[11px]
                   bg-slate-800/70 text-slate-300 border-slate-600/60"
						:title="t('placing.remainingTotal')"
					>
            {{ splatterRemaining }}/{{ splatterTotal }}
          </span>
          <span
						v-else
						class="inline-flex items-center gap-1 rounded-full border px-2 py-[2px] text-[11px]
                   bg-emerald-600/15 text-emerald-300 border-emerald-500/40"
					>
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
              <path d="M20 6L9 17l-5-5" />
            </svg>
          </span>
        </span>
			</div>
		</button>
	</div>
</template>
