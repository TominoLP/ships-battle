import { ref } from 'vue';
import AuthControllerRoutes from '@/actions/App/Http/Controllers/AuthController';
import { api } from '@/src/composables/useApi';
import type { AuthUser } from '@/src/types';

type LoginPayload = {
  name: string;
  password: string;
  remember?: boolean;
};

type RegisterPayload = {
  name: string;
  password: string;
};

function setCsrfToken(token?: string) {
  if (!token) return;
  if (typeof document === 'undefined') return;
  let meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
  if (!meta) {
    meta = document.createElement('meta');
    meta.name = 'csrf-token';
    document.head.appendChild(meta);
  }
  meta.content = token;
}

const currentUser = ref<AuthUser | null>(null);
const isBooting = ref(true);
let bootPromise: Promise<void> | null = null;

async function bootstrap(force = false): Promise<void> {
  if (bootPromise && !force) {
    return bootPromise;
  }

  bootPromise = (async () => {
    try {
      const data = await api<{ user: AuthUser | null }>(
        AuthControllerRoutes.me.get()
      );
      currentUser.value = data?.user ?? null;
      if (data?.user) {
        setCsrfToken(document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content);
      }
    } catch {
      currentUser.value = null;
    } finally {
      isBooting.value = false;
    }
  })();

  return bootPromise;
}

bootstrap().catch(() => {
  // bootstrap errors handled silently; state already set above
});

async function login(payload: LoginPayload): Promise<AuthUser> {
  const data = await api<{ user: AuthUser; csrfToken?: string }>(
    AuthControllerRoutes.login.post(),
    payload
  );
  currentUser.value = data.user;
  setCsrfToken(data.csrfToken);
  isBooting.value = false;
  return data.user;
}

async function register(payload: RegisterPayload): Promise<AuthUser> {
  const data = await api<{ user: AuthUser; csrfToken?: string }>(
    AuthControllerRoutes.register.post(),
    payload
  );
  currentUser.value = data.user;
  setCsrfToken(data.csrfToken);
  isBooting.value = false;
  return data.user;
}

async function logout(): Promise<void> {
  const data = await api<{ csrfToken?: string }>(
    AuthControllerRoutes.logout.post()
  );
  currentUser.value = null;
  setCsrfToken(data?.csrfToken);
  isBooting.value = false;
}

export function useAuth() {
  return {
    user: currentUser,
    booting: isBooting,
    login,
    register,
    logout,
    refresh: () => bootstrap(true),
  };
}
