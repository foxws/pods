import { usePasswordConfirmation } from '@/composables/passkeys'
import { describe, expect, it, vi } from 'vite-plus/test'

vi.mock('@inertiajs/vue3', () => ({ useHttp: () => ({}) }))

describe('usePasswordConfirmation', () => {
  it('runs the action at once while the password is confirmed', async () => {
    const action = vi.fn<() => void>()
    const { confirmOpen, withConfirmedPassword } = usePasswordConfirmation(async () => true)

    await withConfirmedPassword(action)

    expect(action).toHaveBeenCalledOnce()
    expect(confirmOpen.value).toBe(false)
  })

  it('asks for the password first, then runs the action once when it is confirmed', async () => {
    const action = vi.fn<() => void>()
    const { confirmOpen, withConfirmedPassword, onConfirmed } = usePasswordConfirmation(async () => false)

    await withConfirmedPassword(action)

    expect(action).not.toHaveBeenCalled()
    expect(confirmOpen.value).toBe(true)

    await onConfirmed()
    await onConfirmed()

    expect(action).toHaveBeenCalledOnce()
  })
})
