---
name: pods-development
description: >
  Build Pods features that read or change a Podman host. Use when adding or changing a host feature
  (applications, containers, pods, images, volumes, networks, secrets, journal, host settings, Quadlets),
  touching LpodClient, SshHostRunner, SshIdentity, FakeHostRunner or fakeLpod(), the lpod JSON contract
  or its fixtures, pods.yml (ManifestData), installations (Installation, pods:sync), setup steps,
  on-demand, updates, proxy routes, environments, pods:install or pods:verify, or when planning volume backups.
license: MIT
metadata:
  author: foxws
---

# Pods development

Pods is still in development. Read `docs/architecture.md` before you change how Pods talks to hosts, and `docs/roadmap.md` before you build a planned feature. The README documents `pods.yml` and the proxy for users.

## How Pods reaches a host

Page → Inertia controller in `src/Modules/Web` → action in `src/Domain/*/Actions` → `Integrations\Lpod\LpodClient` → `Integrations\Ssh\SshHostRunner` → `ssh` → `lpod … --json` on the host.

- Every environment is an SSH target, the local host too (`host.containers.internal`). Never add a code path that runs `podman`, `systemctl` or `journalctl` from Pods itself.
- Pods only runs lpod. If lpod lacks a command, it needs a PR in [foxws/lpod](https://github.com/foxws/lpod) first. Hosts use lpod from `main` until lpod 4.0 is tagged.
- Podman 6+ and systemd only. Don't add Docker or Podman Compose paths.

## Rules

- **Contract.** Every lpod payload carries `"contract": 1` (`LpodClient::Contract`). Fixtures in `tests/Fixtures/lpod/contract-1/` must match lpod's `tests/fixtures/contract-1/` byte for byte; copy them across instead of editing one side. Pods runs `vp check` over them, so format a new fixture with `pnpm exec vp check --fix` before it goes into the lpod PR.
- **Arguments.** Pass lpod arguments as a list. `SshHostRunner` escapes each with `escapeshellarg` and wraps the command for fish/zsh login shells; don't build shell strings yourself.
- **Secrets.** Send secret values as the runner's `$input` (stdin). Never put them in arguments, props, logs, exceptions or the database. The same goes for passwords a setup step asks for.
- **Caching.** Reads use `$this->cached($environment, 'key', [...])`, or `Cache::flexible` for slow Git reads (catalog, tags). Every write must `forget()` the keys it affects (often `host` too).
- **Timeouts.** Calls default to 30 seconds. Give prune, pull, install, exec (600) and stop-and-wait a longer timeout. Production Pods runs no queue worker, so long host calls run in the request (Octane's 90s limit is CPU time, not time waiting on SSH). Long jobs such as a volume export will need a worker first.
- **Lifecycle.** Start, stop and restart use `--no-block`; the page polls for the result. Use `stopAndWait()` (blocking `APP down`) only when the next call starts units, so a start isn't merged into a pending stop.
- **Octane.** `RunsHostCommands` and `LpodClient` are scoped bindings. Don't keep host or request state in static properties or singletons.
- **Data.** Decode payloads into a spatie/laravel-data object with a `fromLpod()` method in the matching `src/Domain/*/DataObjects`, mark it `#[TypeScript]`, run `composer types:generate`, and re-export new types from `resources/js/types/index.d.ts`.
- **Routes.** Host pages go under `/environments/{environment}` in `routes/web.php`, actions in `routes/actions.php`. Use Wayfinder on the frontend, deferred props with skeletons for slow host data (give a slow one its own group).
- **Errors.** Let `LpodException` surface as a toast (`toast()` in `src/Foundation/Helpers.php`, or `WritesToHost::toHost()`); check how sibling controllers do it. A refusal Pods decides itself before touching the host gets its own domain exception (such as `CannotUpdateApplicationException`).

## Adding a host feature

1. lpod: add the `--json` command and its fixture (see the lpod repo; it has no test suite, so check it with `shellcheck` and stubbed `podman`/`systemctl`/`git` on the PATH, and say it still needs a real-host check). Document it in lpod's `docs/commands.md`.
2. Copy the fixture to `tests/Fixtures/lpod/contract-1/` and add its pattern to `fakeLpod()` in `tests/Pest.php` (more specific patterns first).
3. Add the `LpodClient` method, the data object and the cache keys.
4. Add the action, the controller, the route and the Vue component, following the sibling module.
5. Write feature tests with `fakeLpod()`: assert the props, the exact arguments with `$lpod->assertRan([...])`, stdin for secrets, the order of calls when it matters (`collect($lpod->recorded())->pluck('arguments')->search(...)`), and the error toast when lpod fails (`$lpod->respond('pattern', new HostResult(1, '', 'message'))`). Run `vendor/bin/pest tests/Feature tests/Unit`; the Browser tests hang here.
6. Update `docs/roadmap.md`, the README or `docs/architecture.md` when they describe it, and this skill.

## pods.yml (ManifestData)

`Domain\Quadlets\DataObjects\ManifestData::fromYaml($yaml, $directory)` parses and validates it; invalid manifests throw `InvalidManifestException` and are ignored (shown as `manifest_error`). Keys:

- Catalog metadata (`name`, `label`, `description`, `author`, `icon`, `category`, `website`, `repository`, `notes`) and `sources` (paths relative to the manifest's directory).
- `secrets`: how to ask for each, `generate`, or `from` + `key` of a `format: dotenv` secret (resolved on the server).
- `after_install`: setup steps (`ManifestSetupStepData`): a Quadlet `container`, `user`, `workdir`, a whitespace-split `command`, and `inputs`. An input with `option` goes in as one `--option=VALUE` argument (so a value starting with `-` can't pass as a flag); at most one input has no option and goes on stdin, and a password always does.
- `ondemand`: the keys of lpod's ondemand status, parsed with `OnDemandSettingsData::fromLpod()` and validated with its shared patterns.
- `routes`: role → `upstream` (`CONTAINER:PORT`), `description` and `main` (`ManifestRouteData`); the first is the main one unless another says `main: true`. Hostnames are never in pods.yml.

## Installations

`Domain\Applications\Models\Installation` keeps, per environment and application, the Git source (repository, reference, path, commit), the raw pods.yml (parsed on read, so new keys need no migration), a sha256 checksum per file, the files changed outside Pods, and how each setup step ended (`setup`, never its inputs). `SyncInstallation` compares with `lpod APP files --json` on the application page and in `pods:sync` (daily, 01:00 UTC), ignoring lpod's own drop-ins (`*.d/lpod-*.conf`).

## Built features to follow

- **Setup steps:** `RunSetupStep` resolves the step's Quadlet to the Podman container name through `lpod containers`, runs `lpod container NAME exec [--stdin] --json -- …`, records the exit code, and flashes the output once (`Inertia::flash('setup', …)`). UI: `ApplicationSetupCard.vue`.
- **On-demand:** `lpod APP ondemand enable|disable|status`. The dialog is filled from the stored manifest's `ondemand:` when it has one (`OnDemandSuggestionData::from_manifest`), or else from the Quadlet files (`SuggestOnDemandSettings`). A sleeping app's Start is Wake: lpod's `up` starts `APP-ondemand.service` instead of the `StopWhenUnneeded=yes` main unit.
- **Updates:** `ListApplicationUpdates` offers the newer tags of the stored repository (`lpod quadlet tags`, `version_compare`, pre-releases only after a pre-release). `UpdateApplication` fetches the tag, runs `quadlet check`, refuses missing secrets, then stops and waits, removes the files Pods installed that the tag no longer has (`lpod APP files remove`), and installs with `--replace --start`.

## Proxy routes

lpod generates each application's routes file, with the routes kept on its `# lpod-routes:` line. It writes `sites/APP.Caddyfile` with a site per hostname, or, when the proxy's Caddyfile imports `sites/*.caddy` inside a wildcard site, `sites/APP.caddy` with matchers and `handle`s (`proxy.layout` is `handlers`, and `proxy.domain` is the wildcard domain). It also writes and a `pods-proxy.container.d/lpod-routes-APP.conf` drop-in with the app's networks, from `lpod APP routes set --stdin --json` (`{"routes": [{role, host, upstream, main}]}`). An empty list removes them, and it reloads Caddy or restarts the proxy when the networks change. While an app is on demand, its main route targets its socket (`host.containers.internal:PORT`, reported as `target`). Pods reads `lpod proxy routes --json` (`LpodClient::proxyRoutes()`, cached as `proxy.routes`) for `ProxyRoutesData`, and writes with `setRoutes()`.

- `RouteData` holds the patterns (role, host, upstream), `rules($key)` and `forLpod()` (lowercase hosts, one main route, and `via` dropped from the main route). Use them for any request that takes routes. A route with `via: main` goes where the main route goes and shares its matcher, for apps that route their own hostnames (stry). Every `reverse_proxy` gets `stream_close_delay 5m`, and reloads go through `admin unix//…` when the Caddyfile has it.
- `SuggestRoutes` merges an app's routes with the ones its stored pods.yml names, and suggests `APP.home.arpa` for the main one and `ROLE.APP.home.arpa` for the others, or `APP.DOMAIN` and `APP-ROLE.DOMAIN` behind a wildcard site (one label, so its certificate covers them). The install page gets a deferred `proxy` prop for that. `resources/js/utils/routes.ts` mirrors that suggestion, so keep both in step.
- The application page has a deferred `routes` prop (group `routes`) for `ApplicationRoutesCard.vue` and `ApplicationRoutesModal.vue`, and `PUT applications/{app}/routes` saves them (`ApplicationRoutesController`). The install page sends `routes` with the install, and `QuadletController` sets them afterwards. If that fails, the install still succeeds and the toast says so.
- Inside a `v-for`, Nuxt UI's `ULink` made vue-tsc lose the loop variable, so `ApplicationRoutesCard.vue` uses a plain `<a>`.
- Round 2 (planned in docs/roadmap.md, Proxy): `lpod proxy install|remove|settings|certificate`, with four certificate modes. In Let's Encrypt, local CA and plain HTTP, routes are sites. In the wildcard-through-DNS mode, they're handlers in `sites/APP.caddy` inside `*.DOMAIN`, and hostnames must be one label deep (`APP-ROLE.DOMAIN`). Also planned:
  - a `via: main` route option for apps that route their own hostnames (stry's `ws` and `s3`);
  - `stream_close_delay 5m` on every `reverse_proxy`;
  - the admin API on `unix//config/admin.sock`;
  - a published `ghcr.io/foxws/pods-proxy` image with DNS plugins (your own as an override);
  - port warnings for rootless.

  Until then `pods-proxy` is installed by hand (README, Proxy).

## Volume backups and restores (planned)

Not built yet. The plan in `docs/roadmap.md`: lpod `volume export` and `volume import --stdin` commands around `podman volume export|import`, queued jobs that stream the archive over SSH between the host and a Pods disk, a notification with a temporary signed download link (or the error), a confirmed restore that stops the application's units first, and scheduled pruning of old archives. Bind-backed volumes (`Type=none`, `Options=bind`, such as a media library) must be left out of exports and never imported into. Production Pods needs a queue worker for this. Ask the user before adding a dependency or a new disk.
