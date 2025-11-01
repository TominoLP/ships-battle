<script lang="ts" setup>
import { computed, reactive, ref } from 'vue';
import { useAuth } from '@/src/composables/useAuth';
import { useLang } from '@/src/composables/useLang';
import LocaleSwitch from '@/src/components/Shell/LocaleSwitch.vue';

const { t } = useLang();
const auth = useAuth();

type Mode = 'login' | 'register';
const mode = ref<Mode>('login');

const form = reactive({
	name: '',
	password: '',
	remember: false,
});

const loading = ref(false);
const fieldErrors = reactive<{
	name: string | null;
	password: string | null;
	general: string | null;
}>({
	name: null,
	password: null,
	general: null,
});

const canSubmit = computed(() => form.name.trim().length >= 3 && form.password.trim().length >= 6);

function clearErrors() {
	fieldErrors.name = null;
	fieldErrors.password = null;
	fieldErrors.general = null;
}

function resetForm() {
	form.name = '';
	form.password = '';
	form.remember = false;
}

function switchMode(next: Mode) {
	mode.value = next;
	clearErrors();
}

type ParsedErrors = {
	general?: string | null;
	fields?: Partial<Record<'name' | 'password', string | null>>;
};

async function extractErrors(err: unknown): Promise<ParsedErrors> {
	const fallback = t('authForm.errors.generic');
	const anyErr = err as any;
	const response: Response | undefined = anyErr?.response;
	const parsed: ParsedErrors = {};

	if (response) {
		try {
			// Prefer JSON payloads with { message, errors: { field: [..] } }
			const data = await response.clone().json();
			if (data?.errors && typeof data.errors === 'object') {
				parsed.fields = {};
				if (Array.isArray(data.errors.name) && data.errors.name[0]) parsed.fields.name = data.errors.name[0];
				if (Array.isArray(data.errors.password) && data.errors.password[0]) parsed.fields.password = data.errors.password[0];
			}
			if (typeof data?.message === 'string' && !parsed.general) parsed.general = data.message;
		} catch {
			try {
				const text = await response.clone().text();
				if (text.trim().length && !parsed.general) parsed.general = text.trim();
			} catch {/* ignore */}
		}
	}

	if (anyErr?.message && typeof anyErr.message === 'string') parsed.general = parsed.general ?? anyErr.message;
	if (!parsed.general && !parsed.fields) parsed.general = fallback;

	return parsed;
}

// Extract a clean message from fieldErrors.general (handles raw JSON strings gracefully)
const generalMessage = computed(() => {
	const g = fieldErrors.general;
	if (!g) return '';
	try {
		const jsonish = g.toString().replace(/^[^{[]+/, '');
		const obj = JSON.parse(jsonish);
		if (obj?.message && typeof obj.message === 'string') return obj.message;
	} catch {/* plain string */}
	return g.toString();
});

async function submit() {
	if (!canSubmit.value || loading.value) return;

	loading.value = true;
	clearErrors();

	try {
		if (mode.value === 'login') {
			await auth.login({
				name: form.name.trim(),
				password: form.password,
				remember: form.remember,
			});
		} else {
			await auth.register({
				name: form.name.trim(),
				password: form.password,
			});
		}
		resetForm();
		clearErrors();
	} catch (err) {
		const parsed = await extractErrors(err);
		if (parsed.fields?.name) fieldErrors.name = parsed.fields.name;
		if (parsed.fields?.password) fieldErrors.password = parsed.fields.password;
		if (parsed.general) fieldErrors.general = parsed.general;
	} finally {
		loading.value = false;
	}
}
</script>

<template>
	<div class="max-w-md mx-auto rounded-xl border border-slate-700 bg-slate-900/70 p-6 shadow-lg backdrop-blur">
		<h1 class="text-2xl font-semibold text-slate-100 mb-4 text-center">
			{{ mode === 'login' ? t('authForm.titleLogin') : t('authForm.titleRegister') }}
		</h1>

		<p class="text-sm text-slate-400 mb-6 text-center">
			{{ t('authForm.subtitle') }}
		</p>

		<form class="space-y-4" @submit.prevent="submit">
			<div class="space-y-2">
				<label class="block text-sm font-medium text-slate-300">{{ t('authForm.labels.name') }}</label>
				<input
					v-model="form.name"
					class="w-full rounded-lg bg-slate-800 text-slate-200 placeholder:text-slate-500 border px-3 py-2 transition"
					:class="fieldErrors.name ? 'border-rose-500/80 focus:border-rose-400 focus:ring-rose-400/40' : 'border-slate-600 focus:border-blue-500 focus:ring-blue-500/30'"
					type="text"
					autocomplete="username"
					:placeholder="t('authForm.placeholders.name')"
					@input="fieldErrors.name = null; fieldErrors.general = null"
				/>
				<p v-if="fieldErrors.name" class="text-xs text-rose-300 bg-rose-900/40 border border-rose-700/60 rounded-md px-3 py-1">
					{{ fieldErrors.name }}
				</p>
			</div>

			<div class="space-y-2">
				<label class="block text-sm font-medium text-slate-300">{{ t('authForm.labels.password') }}</label>
				<input
					v-model="form.password"
					class="w-full rounded-lg bg-slate-800 text-slate-200 placeholder:text-slate-500 border px-3 py-2 transition"
					:class="fieldErrors.password ? 'border-rose-500/80 focus:border-rose-400 focus:ring-rose-400/40' : 'border-slate-600 focus:border-blue-500 focus:ring-blue-500/30'"
					type="password"
					:autocomplete="mode === 'login' ? 'current-password' : 'new-password'"
					:placeholder="t('authForm.placeholders.password')"
					@input="fieldErrors.password = null; fieldErrors.general = null"
				/>
				<p v-if="fieldErrors.password" class="text-xs text-rose-300 bg-rose-900/40 border border-rose-700/60 rounded-md px-3 py-1">
					{{ fieldErrors.password }}
				</p>
			</div>

			<label v-if="mode === 'login'" class="flex items-center gap-2 text-sm text-slate-300">
				<input
					v-model="form.remember"
					type="checkbox"
					class="h-4 w-4 rounded border-slate-600 text-blue-500 focus:ring-blue-400 appearance-none bg-slate-700 checked:bg-blue-500 checked:border-blue-500"
				/>
				{{ t('authForm.rememberMe') }}
			</label>

			<transition name="fade">
				<div
					v-if="fieldErrors.general"
					class="text-sm text-rose-100 bg-rose-900/50 border border-rose-700/70 rounded-lg px-4 py-3 flex items-center gap-3 shadow-sm"
				>
					<span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-rose-700/60 text-xs font-semibold">!</span>
					<span>{{ generalMessage }}</span>
				</div>
			</transition>

			<button
				:disabled="!canSubmit || loading"
				class="w-full bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold px-4 py-2 rounded-lg transition-colors"
				type="submit"
			>
				{{ loading ? t('common.pleaseWait') : (mode === 'login' ? t('authForm.buttons.login') : t('authForm.buttons.register')) }}
			</button>
		</form>

		<div class="mt-6 text-sm text-center text-slate-400">
			<template v-if="mode === 'login'">
				<div class="flex items-center justify-between">
					<div>
						{{ t('authForm.switch.noAccount') }}
						<button class="text-blue-400 hover:text-blue-300 font-medium" type="button" @click="switchMode('register')">
							{{ t('authForm.switch.createNow') }}
						</button>
					</div>
					<LocaleSwitch @refresh="null" />
				</div>
			</template>
			<template v-else>
				{{ t('authForm.switch.alreadyRegistered') }}
				<button class="text-blue-400 hover:text-blue-300 font-medium" type="button" @click="switchMode('login')">
					{{ t('authForm.switch.toLogin') }}
				</button>
			</template>
		</div>
	</div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity .18s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
