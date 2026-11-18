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
export type ApplicationOperation = 'up' | 'down' | 'force-stop' | 'restart' | 'redeploy'
export type ApplicationRoutesData = {
  proxy_installed: boolean
  proxy_running: boolean
  layout: string
  domain: string | null
  default_domain: string | null
  routes: RouteSuggestionData[]
}
export type CatalogApplicationData = {
  name: string
  repository: string
  reference: string
  path: string
  manifest: ManifestData
}
export type CatalogData = {
  applications: CatalogApplicationData[]
  errors: string[]
}
export type ContainerData = {
  name: string
  image: string | null
  state: string
  cpu: number | null
  memory: number | null
}
export type ContainerDetailData = {
  container: HostContainerData
  created_at: string | null
  memory_limit: number | null
  restart_policy: string | null
  restarts: number
  user: string | null
  auto_update: string | null
  digest: string | null
  health_check: {
    command: string
    interval: string | null
    last_check: string | null
  } | null
  mounts: ContainerMountData[]
  networks: {
    name: string
    ip: string | null
  }[]
  secrets: {
    name: string
    type: string
    target: string
  }[]
  environment: {
    name: string
    value: string
  }[]
}
export type ContainerExecResultData = {
  exit_code: number
  stdout: string
  stderr: string
}
export type ContainerMountData = {
  type: string
  source: string
  destination: string
  mode: string | null
}
export type ContainerOperation = 'start' | 'stop' | 'restart'
export type ContainerStatsData = {
  cpu: number | null
  memory: number | null
  memory_limit: number | null
  net_in: number | null
  net_out: number | null
  block_in: number | null
  block_out: number | null
  pids: number | null
  sampled_at: string | null
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
export type GitTagData = {
  name: string
  commit: string
}
export type HostContainerData = {
  id: string
  name: string
  image: string | null
  state: string
  health: string | null
  exit_code: number | null
  application: string | null
  quadlet: string | null
  unit: string | null
  pod: string | null
  ports: string[]
  cpu: number | null
  memory: number | null
  started_at: string | null
}
export type HostEventData = {
  time: string | null
  type: string
  action: string
  name: string
  image: string | null
  application: string | null
  exit_code: number | null
}
export type HostSettingsData = {
  auto_update: boolean
  schedule: string | null
  rollback: boolean | null
  next_run: string | null
  last_run: string | null
  linger: boolean | null
  quadlet_dir: string | null
  editable: boolean
}
export type HostStatsData = {
  cpu_cores: number
  cpu_usage: number
  cpu_load: number[] | null
  memory_used: number
  memory_total: number
  swap_used: number | null
  swap_total: number | null
  disk_used: number
  disk_total: number
  containers_running: number
  containers_total: number
  auto_update_enabled: boolean
  auto_update_next_run: string | null
  os: string | null
  kernel: string | null
  booted_at: string | null
  storage_driver: string | null
  network_backend: string | null
  quadlet_dir: string | null
  linger: boolean | null
  units_active: number | null
  units_failed: number | null
}
export type HostVolumeData = {
  name: string
  quadlet: string | null
  driver: string
  mountpoint: string | null
  size: number | null
  type: string
  containers: string[]
  applications: string[]
}
export type ImageData = {
  id: string
  repository: string | null
  tag: string | null
  size: number | null
  created_at: string | null
  used_by: string[]
  containers: number
  dangling: boolean
  auto_update: string | null
  update: string | null
}
export type InstallationData = {
  repository: string | null
  reference: string | null
  path: string | null
  commit: string | null
  manifest: ManifestData | null
  changed: boolean
  modified: string[]
  added: string[]
  removed: string[]
  setup: Record<
    string,
    {
      exit_code: number
      ran_at: string
    }
  >
  updated_from: string | null
  installed_at: string | null
  checked_at: string | null
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
export type ManifestData = {
  name: string | null
  label: string | null
  description: string | null
  author: string | null
  icon: string | null
  category: string | null
  website: string | null
  repository: string | null
  notes: string | null
  directory: string
  sources: ManifestSourceData[]
  secrets: ManifestSecretData[]
  after_install: ManifestSetupStepData[]
  after_update: ManifestSetupStepData[]
  ondemand: OnDemandSettingsData | null
  routes: ManifestRouteData[]
  timers: ManifestTimerData[]
}
export type ManifestRouteData = {
  role: string
  upstream: string
  description: string | null
  main: boolean
  via: string | null
}
export type ManifestSecretData = {
  name: string
  description: string | null
  format: string
  generate: boolean
  from: string | null
  key: string | null
}
export type ManifestSetupInputData = {
  name: string
  label: string
  description: string | null
  type: string
  option: string | null
}
export type ManifestSetupStepData = {
  name: string
  label: string
  description: string | null
  container: string
  user: string | null
  workdir: string | null
  command: string
  inputs: ManifestSetupInputData[]
}
export type ManifestSourceData = {
  name: string
  path: string
  description: string | null
}
export type ManifestTimerData = {
  name: string
  description: string | null
  container: string
  on_calendar: string[]
  persistent: boolean | null
  randomized_delay_sec: string | null
  timeout_start_sec: string | null
}
export type NetworkData = {
  name: string
  quadlet: string | null
  driver: string
  subnet: string | null
  gateway: string | null
  dns: boolean
  internal: boolean
  ipv6: boolean
  containers: number
  applications: string[]
  default: boolean
}
export type OnDemandData = {
  enabled: boolean
  listening: boolean
  awake: boolean
  settings: OnDemandSettingsData | null
}
export type OnDemandIdleCheckData = {
  container: string
  user: string | null
  command: string
}
export type OnDemandScheduleData = {
  container: string
  at: string[]
  environment: Record<string, string>
}
export type OnDemandSettingsData = {
  port: number | null
  app_port: number | null
  listen: string | null
  idle_timeout: string
  workers: string[]
  idle_check: OnDemandIdleCheckData | null
  schedule: OnDemandScheduleData | null
}
export type OnDemandSuggestionData = {
  laravel: boolean
  containers: string[]
  settings: OnDemandSettingsData
  from_manifest: boolean
}
export type PaginatedDataCollection<TKey, TValue> = LengthAwarePaginator<TKey, TValue>
export type PodData = {
  id: string
  name: string
  status: string
  application: string | null
  quadlet: string | null
  unit: string | null
  containers: number
  running: number
  ports: string[]
  networks: string[]
  created_at: string | null
}
export type ProxyRoutesData = {
  installed: boolean
  running: boolean
  layout: string
  domain: string | null
  routes: RouteData[]
}
export type ProxySettingsData = {
  installed: boolean
  running: boolean
  managed: boolean
  mode: string | null
  email: string | null
  domain: string | null
  dns_provider: string | null
  resolvers: string | null
  image: string | null
  http_port: number | null
  https_port: number | null
  dns_token: boolean
  rootless: boolean
  unprivileged_port_start: number | null
}
export type PruneResultData = {
  removed: string[]
  reclaimed: number | null
}
export type QuadletCheckData = {
  valid: boolean
  errors: string[]
  units: string[]
}
export type QuadletFileData = {
  name: string
  path: string
  content: string
}
export type QuadletSourceData = {
  commit: string | null
  files: {
    name: string
    content: string
  }[]
  manifest: ManifestData | null
  manifest_error: string | null
  manifest_yaml: string | null
  manifest_path: string | null
}
export type RouteData = {
  application: string | null
  role: string
  host: string
  upstream: string
  main: boolean
  via: string | null
  target: string
}
export type RouteSuggestionData = {
  role: string
  description: string | null
  upstream: string
  main: boolean
  via: string | null
  host: string | null
  suggested_host: string
  target: string | null
  from_manifest: boolean
}
export type SearchResultData = {
  group: string
  label: string
  description: string | null
  url: string
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
export type TimerData = {
  name: string
  unit: string
  container: string
  service: string
  on_calendar: string[]
  persistent: boolean | null
  randomized_delay_sec: string | null
  timeout_start_sec: string | null
  enabled: boolean
  running: boolean
  next_run: string | null
  last_run: string | null
  last_result: string | null
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
