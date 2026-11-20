import type { EchoConfig } from '@/types'
import { configureEcho, echo } from '@laravel/echo-vue'

// An on-demand Pods sleeps after 10 minutes without a connection, and an open
// WebSocket counts as one, so a tab that stays hidden lets it go.
const disconnectHiddenAfter = 60_000

export function bootEcho(config: EchoConfig | undefined): void {
  // `useEcho()` throws when Echo is unconfigured, so SSR (no `window`) and
  // missing config fall back to the no-op `null` broadcaster.
  if (typeof window === 'undefined' || !config || !config.key) {
    configureEcho({ broadcaster: 'null' })

    return
  }

  configureEcho({
    broadcaster: 'reverb',
    key: config.key,
    enabledTransports: ['ws', 'wss'],
    ...connectionOptions(config),
  })

  disconnectWhileHidden()
}

/**
 * Without a host of its own, Reverb is on the page's address: production Pods forwards its path to Reverb.
 */
function connectionOptions(config: EchoConfig) {
  if (config.host) {
    return {
      wsHost: config.host,
      wsPort: config.port,
      wssPort: config.port,
      forceTLS: config.scheme === 'https',
    }
  }

  const isSecure = window.location.protocol === 'https:'
  const port = Number(window.location.port) || (isSecure ? 443 : 80)

  return {
    wsHost: window.location.hostname,
    wsPort: port,
    wssPort: port,
    forceTLS: isSecure,
  }
}

/**
 * Pusher keeps its channels and subscribes to them again when it reconnects (Echo's own `connect()`
 * would start a new client without them), and Inertia then refreshes every live prop, so nothing is missed.
 */
function disconnectWhileHidden(): void {
  let timer: ReturnType<typeof setTimeout> | undefined

  document.addEventListener('visibilitychange', () => {
    const { pusher } = echo<'reverb'>().connector

    clearTimeout(timer)

    if (document.visibilityState === 'hidden') {
      timer = setTimeout(() => pusher.disconnect(), disconnectHiddenAfter)

      return
    }

    if (pusher.connection.state === 'disconnected') {
      pusher.connect()
    }
  })
}
