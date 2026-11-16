---
name: pods-development
description: >
  Build Pods features that read or change a Podman host. Use when adding or changing a host feature
  (applications, containers, pods, images, volumes, networks, secrets, journal, host settings, Quadlets),
  touching LpodClient, SshHostRunner, SshIdentity, FakeHostRunner or fakeLpod(), the lpod JSON contract
  or its fixtures, environments, pods:install or pods:verify, or when planning volume backups.
license: MIT
metadata:
  author: foxws
---

# Pods development

Pods is still in development. Read `docs/architecture.md` before you change how Pods talks to hosts, and `docs/roadmap.md` before you build a planned feature.

## How Pods reaches a host

Page → Inertia controller in `src/Modules/Web` → `Integrations\Lpod\LpodClient` → `Integrations\Ssh\SshHostRunner` → `ssh` → `lpod … --json` on the host.

- Every environment is an SSH target, the local host too (`host.containers.internal`). Never add a code path that runs `podman`, `systemctl` or `journalctl` from Pods itself.
- Pods only runs lpod. If lpod lacks a command, it needs a PR in [foxws/lpod](https://github.com/foxws/lpod) first.
- Podman 6+ and systemd only. Don't add Docker or Podman Compose paths.

## Rules

- **Contract.** Every lpod payload carries `"contract": 1` (`LpodClient::Contract`). Fixtures in `tests/Fixtures/lpod/contract-1/` must match lpod's `tests/fixtures/contract-1/` byte for byte; copy them across instead of editing one side.
- **Arguments.** Pass lpod arguments as a list. `SshHostRunner` escapes each with `escapeshellarg` and wraps the command for fish/zsh login shells; don't build shell strings yourself.
- **Secrets.** Send secret values as the runner's `$input` (stdin). Never put them in arguments, props, logs, exceptions or the database.
- **Caching.** Reads use `$this->cached($environment, 'key', [...])`. Every write must `forget()` the keys it affects (often `host` too).
- **Timeouts.** Calls default to 30 seconds. Give prune, pull and install a longer timeout, and long jobs (such as a volume export) a queued job instead of a request.
- **Lifecycle.** Start, stop and restart use `--no-block`; the page polls for the result.
- **Octane.** `RunsHostCommands` and `LpodClient` are scoped bindings. Don't keep host or request state in static properties or singletons.
- **Data.** Decode payloads into a spatie/laravel-data object with a `fromLpod()` method in the matching `src/Domain/*/DataObjects`, mark it `#[TypeScript]` and run `composer types:generate`.
- **Routes.** Host pages go under `/environments/{environment}` in `routes/web.php`, actions in `routes/actions.php`. Use Wayfinder on the frontend, deferred props with skeletons for slow host data.
- **Errors.** Let `LpodException` and `UnsupportedContractException` surface as flash messages; check how sibling controllers do it.

## Adding a host feature

1. lpod: add the `--json` command and its fixture (see the lpod repo; it has no test suite, so check it with `shellcheck` and stubbed binaries, and say it still needs a real-host check).
2. Copy the fixture to `tests/Fixtures/lpod/contract-1/` and add its pattern to `fakeLpod()` in `tests/Pest.php`.
3. Add the `LpodClient` method, the data object and the cache keys.
4. Add the controller, the route and the Vue page, following the sibling module.
5. Write feature tests with `fakeLpod()`: assert the props, the exact arguments with `$lpod->assertRan([...])`, stdin for secrets, and the error flash when lpod fails (`$lpod->respond('pattern', new HostResult(1, '', 'message'))`).

## Volume backups (planned)

Not built yet. The plan in `docs/roadmap.md`: an lpod `volume export` command that streams `podman volume export`, a queued job that streams the archive over SSH to a Pods disk, a notification with a temporary signed download link when it's ready (or the error when it fails), and scheduled pruning of old archives. Ask the user before adding a dependency or a new disk.
