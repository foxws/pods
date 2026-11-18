import type { Locale } from '@/types/generated'
import type { SelectItem } from '@nuxt/ui'

export type FlashType = 'success' | 'error' | 'warning' | 'info' | 'primary'

export type FlashData = {
  readonly title?: string
  readonly description?: string
  readonly type?: FlashType
  /** What a setup step printed, flashed once after it ran. */
  readonly setup?: SetupOutput
}

export type SetupOutput = {
  readonly step: string
  readonly exit_code: number
  readonly output: string
}

export type Features = {
  readonly registration: boolean
  readonly resetPasswords: boolean
  readonly twoFactor: boolean
  readonly passkeys: boolean
}

export type EchoConfig = {
  readonly key: string
  readonly host: string
  readonly port: number
  readonly scheme: string
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
  ApplicationRoutesData,
  CatalogApplicationData,
  CatalogData,
  ContainerData,
  ContainerDetailData,
  ContainerMountData,
  ContainerOperation,
  ContainerStatsData,
  EnvironmentData,
  GitTagData,
  HostContainerData,
  HostEventData,
  HostSettingsData,
  HostStatsData,
  HostVolumeData,
  ImageData,
  InstallationData,
  JournalEntryData,
  ManifestData,
  ManifestRouteData,
  ManifestSecretData,
  ManifestSetupInputData,
  ManifestSetupStepData,
  ManifestSourceData,
  ManifestTimerData,
  NetworkData,
  OnDemandData,
  OnDemandSettingsData,
  OnDemandSuggestionData,
  PodData,
  ProxyRoutesData,
  ProxySettingsData,
  QuadletCheckData,
  QuadletFileData,
  QuadletSourceData,
  RouteSuggestionData,
  SearchResultData,
  SecretData,
  TimerData,
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

export type ProxyMode = 'letsencrypt' | 'local' | 'wildcard' | 'http'

/**
 * Foundation\Settings\ProxySettings: what a host's Proxy section starts from, and the domain
 * hostnames are suggested under.
 */
export type ProxyDefaults = {
  mode: ProxyMode
  email: string | null
  domain: string | null
  dns_provider: string
  resolvers: string | null
  http_port: number
  https_port: number
  image: string | null
}

/**
 * The fields of a Proxy form, as ProxySettingsFields edits them.
 */
export type ProxySettingsFormValues = {
  mode: ProxyMode
  email: string
  domain: string
  dns_provider: string
  dns_token: string
  resolvers: string
  http_port: number
  https_port: number
  image: string
}

export type Passkey = {
  id: number
  name: string
  authenticator: string | null
  last_used_at: string | null
  created_at: string
}
