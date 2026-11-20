import { bootEcho } from '@/plugins/echo'
import type { EchoConfig } from '@/types'
import { configureEcho } from '@laravel/echo-vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vite-plus/test'

const pusher = {
  connection: { state: 'connected' },
  connect: vi.fn<() => void>(),
  disconnect: vi.fn<() => void>(),
}

vi.mock('@laravel/echo-vue', () => ({
  configureEcho: vi.fn<(...args: unknown[]) => void>(),
  echo: () => ({ connector: { pusher } }),
}))

const config: EchoConfig = { key: 'app-key', host: 'reverb.test', port: 443, scheme: 'https' }

let visibilityListener: (() => void) | undefined
const fakeDocument = {
  visibilityState: 'visible',
  addEventListener: (_event: string, listener: () => void) => {
    visibilityListener = listener
  },
}

function changeVisibility(state: 'visible' | 'hidden'): void {
  fakeDocument.visibilityState = state
  visibilityListener?.()
}

describe('bootEcho', () => {
  beforeEach(() => {
    vi.stubGlobal('window', { location: { protocol: 'http:', hostname: '127.0.0.1', port: '9393' } })
    vi.stubGlobal('document', fakeDocument)
    fakeDocument.visibilityState = 'visible'
    pusher.connection.state = 'connected'
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.clearAllMocks()
    vi.useRealTimers()
  })

  it('configures the reverb broadcaster in the browser', () => {
    bootEcho(config)

    expect(configureEcho).toHaveBeenCalledWith(
      expect.objectContaining({ broadcaster: 'reverb', key: 'app-key', wsHost: 'reverb.test', forceTLS: true }),
    )
  })

  it('reaches reverb on the page address without a host of its own', () => {
    bootEcho({ ...config, host: '' })

    expect(configureEcho).toHaveBeenCalledWith(
      expect.objectContaining({ wsHost: '127.0.0.1', wsPort: 9393, wssPort: 9393, forceTLS: false }),
    )
  })

  it('disconnects a tab hidden for a minute, and connects again when it is shown', () => {
    vi.useFakeTimers()
    bootEcho(config)

    changeVisibility('hidden')
    vi.advanceTimersByTime(59_000)
    expect(pusher.disconnect).not.toHaveBeenCalled()

    vi.advanceTimersByTime(1_000)
    expect(pusher.disconnect).toHaveBeenCalledOnce()

    pusher.connection.state = 'disconnected'
    changeVisibility('visible')
    expect(pusher.connect).toHaveBeenCalledOnce()
  })

  it('stays connected when a tab is shown again within the minute', () => {
    vi.useFakeTimers()
    bootEcho(config)

    changeVisibility('hidden')
    vi.advanceTimersByTime(30_000)
    changeVisibility('visible')
    vi.advanceTimersByTime(60_000)

    expect(pusher.disconnect).not.toHaveBeenCalled()
    expect(pusher.connect).not.toHaveBeenCalled()
  })

  it('falls back to the null broadcaster during SSR so useEcho does not throw', () => {
    vi.stubGlobal('window', undefined)

    bootEcho(config)

    expect(configureEcho).toHaveBeenCalledExactlyOnceWith({ broadcaster: 'null' })
  })

  it('falls back to the null broadcaster when the config is missing', () => {
    bootEcho(undefined)

    expect(configureEcho).toHaveBeenCalledExactlyOnceWith({ broadcaster: 'null' })
  })
})
