/* eslint-disable @typescript-eslint/no-explicit-any */
import { ref, onBeforeUnmount } from 'vue'
import type { Ref } from 'vue'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

// Make Pusher available for Echo (Laravel Reverb is Pusher-compatible)
if (typeof window !== 'undefined') {
    ;(window as any).Pusher = Pusher
}

// Cache per game to avoid multiple sockets across HMR/component remounts
const registry = new Map<number, {
    // @ts-ignore
    echo: Echo
    channel: ReturnType<Echo<'pusher'>['channel']>
    messages: Ref<string[]>
    refs: number // simple ref-count
}>()

type UseGameSocketApi = {
    echo: Echo<'pusher'>
    channel: ReturnType<Echo<'pusher'>['channel']>
    messages: Ref<string[]>
    on: (event: string, handler: (data: any) => void) => void
    off: (event: string) => void
    leave: () => void
    disconnect: () => void
}

/**
 * useGameSocket(gameId)
 * - Returns a stable Echo channel for "game.{gameId}"
 * - .on('event', cb) listens to ".event" on the channel
 */
export function useGameSocket(gameId: number): UseGameSocketApi {
    // SSR guard
    if (typeof window === 'undefined') {
        const noop = () => {}
        const empty: any[] = []
        return {
            echo: {} as Echo<'pusher'>,
            channel: {} as any,
            messages: ref<string[]>([]),
            on: noop,
            off: noop,
            leave: noop,
            disconnect: noop,
        }
    }

    if (!registry.has(gameId)) {
        const messages = ref<string[]>([])
        
        const echo = new Echo({
            broadcaster: 'pusher',
            key: 'reverb',
            wsHost: window.location.hostname,
            wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
            wssPort: Number(import.meta.env.VITE_REVERB_WSS_PORT ?? 443),
            forceTLS: Boolean(import.meta.env.VITE_REVERB_TLS ?? false),
            disableStats: true,
            enabledTransports: ['ws', 'wss'],
            cluster: '',
            // wsPath: import.meta.env.VITE_REVERB_PATH || undefined,
            // authEndpoint: '/broadcasting/auth',
        })

        // Lifecycle logs
        const pusher = (echo as any).connector?.pusher
        if (pusher?.connection) {
            pusher.connection.bind('connected', () => {
                push(`🔌 Connected (${echo.options.wsHost}:${echo.options.wsPort ?? echo.options.wssPort}${echo.options.wsPath ?? ''})`)
            })
            pusher.connection.bind('error', (err: any) => push(`⚠️ Connect error: ${err?.message ?? err}`))
            pusher.connection.bind('state_change', (s: any) => push(`🔄 State: ${s?.previous} → ${s?.current}`))
        }

        function push(msg: string) {
            messages.value.unshift(`[${new Date().toLocaleTimeString()}] ${msg}`)
        }

        const channel = echo.channel(`game.${gameId}`)

        registry.set(gameId, { echo, channel, messages, refs: 0 })
    }

    const entry = registry.get(gameId)!
    entry.refs++

    // helpers
    function on(event: string, handler: (data: any) => void) {
        entry.channel.listen(`.${event}`, (payload: any) => {
            try { handler(payload) } finally {
                entry.messages.value.unshift(`[${new Date().toLocaleTimeString()}] ${event}: ${JSON.stringify(payload)}`)
            }
        })
    }

    function off(event: string) {
        entry.channel.stopListening(`.${event}`)
    }

    function leave() {
        entry.echo.leaveChannel(`game.${gameId}`)
    }

    function disconnect() {
        entry.echo.disconnect()
    }

    // cleanup ref-count when the component using this composable unmounts
    onBeforeUnmount(() => {
        entry.refs--
        entry.echo.leaveChannel(`game.${gameId}`)
        if (entry.refs <= 0) {
            registry.delete(gameId)
        }
    })

    return {
        echo: entry.echo,
        channel: entry.channel,
        messages: entry.messages,
        on,
        off,
        leave,
        disconnect,
    }
}
