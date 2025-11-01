type WayfinderCall = { url: string; method: string }
type JsonBody = Record<string, unknown> | undefined

function buildHeaders(init?: RequestInit): Record<string, string> {
	const lang = (localStorage.getItem('locale') || navigator.language || 'en').slice(0,2);
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
		'X-Locale': lang === 'de' ? 'de' : 'en',
  };

  if (init?.headers) {
    if (init.headers instanceof Headers) {
      init.headers.forEach((value, key) => {
        headers[key] = value;
      });
    } else if (Array.isArray(init.headers)) {
      for (const [key, value] of init.headers) {
        headers[key] = value;
      }
    } else {
      Object.assign(headers, init.headers as Record<string, string>);
    }
  }

  if (typeof document !== 'undefined') {
    const token = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;
    if (token && !headers['X-CSRF-TOKEN']) {
      headers['X-CSRF-TOKEN'] = token;
    }
  }

  return headers;
}

export async function api<T = unknown>(call: WayfinderCall, body?: JsonBody, init?: RequestInit): Promise<T> {
  const method = call.method?.toUpperCase?.() ?? 'GET';
  const headers = buildHeaders(init);
  const res = await fetch(call.url, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
    credentials: 'same-origin',
    ...init
  });
  if (!res.ok) {
    const text = await res.text().catch(() => '');
    throw Object.assign(new Error(`HTTP ${res.status}: ${text || res.statusText}`), { response: res });
  }
  try {
    return await res.json() as T;
  } catch {
    return undefined as T;
  }
}
