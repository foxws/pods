import type { Locale } from '@/types/generated'
import type { SelectItem } from '@nuxt/ui'

export type FlashType = 'success' | 'error' | 'warning' | 'info' | 'primary'

export type FlashData = {
  readonly title?: string
  readonly description?: string
  readonly type?: FlashType
}

export type Features = {
  readonly registration: boolean
  readonly resetPasswords: boolean
  readonly twoFactor: boolean
  readonly passkeys: boolean
}

export type OptionItem = SelectItem & {
  label: string
  value: string | number | boolean | null
  disabled?: boolean
}

export type { StateData, UserData as User } from '@/types/generated'

export type {
  ApplicationData,
  ApplicationDetailData,
  ApplicationOperation,
  ContainerData,
  EnvironmentData,
  HostStatsData,
  JournalEntryData,
  QuadletFileData,
  SecretData,
  UnitStatus,
} from '@/types/generated'

/**
 * A value read from an environment's host, or why it could not be read.
 */
export type HostProperty<T> = {
  readonly data: T | null
  readonly error: string | null
}

export type ApplicationSettings = {
  site_name: string
  timezone: string
  default_locale: Locale
  allow_registration: boolean
}

export type Passkey = {
  id: number
  name: string
  authenticator: string | null
  last_used_at: string | null
  created_at: string
}
