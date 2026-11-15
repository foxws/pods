export type ApplicationData = {
  name: string
  unit: string
  kind: string
  status: UnitStatus
  result: string | null
  since: string | null
  image: string | null
  pods: number
  containers: number
  ports: string[]
  memory: number | null
  auto_update: string | null
  description: string | null
  url: string | null
  error: string | null
}
export type ApplicationDetailData = {
  application: ApplicationData
  path: string | null
  volumes: VolumeData[]
  secrets: string[]
  items: ContainerData[]
}
export type ApplicationOperation = 'up' | 'down' | 'restart'
export type ContainerData = {
  name: string
  image: string | null
  state: string
  cpu: number | null
  memory: number | null
}
export type CursorPaginatedDataCollection<TKey, TValue> = CursorPaginator<TKey, TValue>
export type CursorPaginator<TKey, TValue> = {
  data: TKey extends string ? Record<TKey, TValue> : TValue[]
  links: {
    url: string | null
    label: string
    active: boolean
  }[]
  meta: {
    path: string
    per_page: number
    next_cursor: string | null
    next_page_url: string | null
    prev_cursor: string | null
    prev_page_url: string | null
  }
}
export type CursorPaginatorInterface<TKey, TValue> = CursorPaginator<TKey, TValue>
export type EnvironmentData = {
  id: string
  name: string
  host: string
  port: number
  user: string
  is_local: boolean
  scope: string | null
  podman: string | null
  lpod: string | null
  hostname: string | null
  last_seen_at: string | null
}
export type HostStatsData = {
  cpu_cores: number
  cpu_usage: number
  memory_used: number
  memory_total: number
  disk_used: number
  disk_total: number
  containers_running: number
  containers_total: number
  auto_update_next_run: string | null
}
export type JournalEntryData = {
  time: string
  unit: string
  priority: number
  message: string
}
export type Language = 'en' | 'nl'
export type LengthAwarePaginator<TKey, TValue> = {
  data: TKey extends string ? Record<TKey, TValue> : TValue[]
  links: {
    url: string | null
    label: string
    active: boolean
  }[]
  meta: {
    total: number
    current_page: number
    first_page_url: string
    from: number | null
    last_page: number
    last_page_url: string
    next_page_url: string | null
    path: string
    per_page: number
    prev_page_url: string | null
    to: number | null
  }
}
export type LengthAwarePaginatorInterface<TKey, TValue> = LengthAwarePaginator<TKey, TValue>
export type Locale = 'en-US' | 'nl-NL'
export type PaginatedDataCollection<TKey, TValue> = LengthAwarePaginator<TKey, TValue>
export type QuadletFileData = {
  name: string
  path: string
  content: string
}
export type SecretData = {
  name: string
  driver: string | null
  updated_at: string | null
  used_by: string[]
  missing: boolean
}
export type StateData = {
  name: string
  label: string
  icon: string
  color: string
}
export type UnitStatus = 'running' | 'activating' | 'deactivating' | 'reloading' | 'failed' | 'inactive' | 'unknown'
export type UserData = {
  id: string
  name: string | null
  email: string
  state: StateData
  two_factor_enabled: boolean
  roles?: string[]
  permissions?: string[]
  email_verified_at: string | null
  created_at: string | null
  updated_at: string | null
}
export type VolumeData = {
  name: string
  size: number | null
}
