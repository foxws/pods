import type { EchoConfig, EnvironmentData, Features, FlashData, OptionItem, User } from '@/types'
import type Pusher from 'pusher-js'

declare global {
  interface Window {
    Pusher: typeof Pusher
  }
}

declare module '@inertiajs/core' {
  export interface InertiaConfig {
    readonly flashDataType: FlashData
  }

  export interface PageProps {
    readonly app: string
    readonly nonce: string
    readonly locale: string
    readonly locales: OptionItem[] | undefined
    readonly languages: OptionItem[] | undefined
    /** The choices of how often an idle host page reloads, from HostRefresh. */
    readonly hostRefreshes: OptionItem[] | undefined
    /** The labels of the unit statuses, from UnitStatus. */
    readonly unitStatuses: OptionItem[] | undefined
    /** The labels of the volume export statuses, from VolumeExportStatus. */
    readonly volumeExportStatuses: OptionItem[] | undefined
    readonly features: Features | undefined
    readonly auth: User | null | undefined
    readonly environments: EnvironmentData[] | undefined
    readonly environment?: EnvironmentData
    readonly echo: EchoConfig | undefined
    /** The number of unread notifications. */
    readonly notifications: number | undefined
    /** Seconds between reloads of an idle host page; 0 turns them off. */
    readonly refresher: number | undefined
  }
}
