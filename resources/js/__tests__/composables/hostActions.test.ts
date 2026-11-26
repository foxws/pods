import { useLiveVisit } from '@/composables/hostActions'
import { describe, expect, it, vi } from 'vite-plus/test'
import { reactive } from 'vue'

const listener = [{ channel: { name: 'environments.01HOST.apps', type: 'private' }, events: ['environment.changed'] }]

const page = reactive<{ props: Record<string, unknown>; liveProps?: Record<string, { listeners: typeof listener }> }>({
  props: {},
})

vi.mock('@inertiajs/vue3', () => ({
  router: { visit: vi.fn<() => void>() },
  usePage: () => page,
}))

describe('useLiveVisit', () => {
  it('reloads only the live props the page has loaded, and the props it is asked for', () => {
    page.props = { application: { data: null, error: null }, files: { data: [], error: null }, exports: [], logs: [] }
    // `routes` is still loading, so its own deferred request brings it.
    page.liveProps = {
      application: { listeners: listener },
      files: { listeners: listener },
      routes: { listeners: listener },
    }

    expect(useLiveVisit()(['exports'])).toEqual({
      preserveScroll: true,
      preserveState: true,
      only: ['application', 'files', 'exports'],
    })
  })

  it('reloads the whole page, keeping its state, when it has no live props', () => {
    page.props = { environment: {} }
    page.liveProps = undefined

    expect(useLiveVisit()()).toEqual({ preserveScroll: true, preserveState: true })
  })
})
