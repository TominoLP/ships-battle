import GameController from '@/actions/App/Http/Controllers/GameController'
import type { CreateGameResponse, JoinGameResponse, PlaceShipsResponse, ShootResponse, PlacedShip } from '@/src/types'

type WayfinderCall = { url: string; method: string }
type JsonBody = Record<string, unknown> | undefined

export async function api<T = unknown>(call: WayfinderCall, body?: JsonBody, init?: RequestInit): Promise<T> {
  const method = call.method?.toUpperCase?.() ?? 'GET'
  const headers: HeadersInit = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...init?.headers,
  }
  const res = await fetch(call.url, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
    credentials: 'same-origin',
    ...init,
  })
  if (!res.ok) {
    const text = await res.text().catch(() => '')
    throw Object.assign(new Error(`HTTP ${res.status}: ${text || res.statusText}`), { response: res })
  }
  try { return await res.json() as T } catch { return undefined as T }
}