# Pods

[![Tests](https://github.com/foxws/pods/actions/workflows/tests.yml/badge.svg)](https://github.com/foxws/pods/actions/workflows/tests.yml)
[![Code Quality](https://github.com/foxws/pods/actions/workflows/code-quality.yml/badge.svg)](https://github.com/foxws/pods/actions/workflows/code-quality.yml)
[![License](https://img.shields.io/github/license/foxws/pods)](LICENSE)

A self-hosted web UI for [Podman Quadlet](https://docs.podman.io/en/latest/markdown/podman-systemd.unit.5.html), made for homelabs. Pods manages the applications, containers, pods, images, volumes, networks, secrets and journal of one or more Podman hosts, over SSH.

> [!WARNING]
> **Pods is still in development.** Expect breaking changes, incomplete features and rough edges. The lpod JSON contract, the database schema and the routes can change between commits, and some lpod commands have not been tested on a real Podman host yet. Don't point Pods at a host you can't afford to lose, and **back up your volumes yourself** (see [Backups](#backups)). Pods doesn't do that yet.

## How it works

```text
Browser ──HTTP──▶ Pods (Laravel) ──SSH──▶ host ──▶ lpod … --json ──▶ podman, systemctl, journalctl
```

- **Every host is an SSH target, the local one too.** Pods runs in a container and reaches the machine it runs on through `host.containers.internal`, so local and remote hosts use the same code path.
- **[lpod](https://github.com/foxws/lpod) is the host agent.** Pods never runs `podman` or `systemctl` itself. It only runs `lpod … --json` and reads the JSON it returns. The CLI and the UI do the same thing, and you can always fall back to `lpod` in a terminal.
- **The JSON contract is versioned.** Every payload carries `"contract": 1`. Pods refuses a host that speaks a contract it doesn't know. The fixtures in `tests/Fixtures/lpod/contract-1/` are the spec lpod implements.
- **Quadlets are the source of truth.** An application is a group of Quadlet units (`.container`, `.pod`, `.kube`, `.volume`, `.network`) as reported by `podman quadlet list`. Pods stores no copy of your containers. It only stores the environments (hosts) and its own users.
- **One SSH key per Pods instance.** `pods:install` generates an ed25519 key pair. You add the public key to `~/.ssh/authorized_keys` of the user that owns the Quadlets. When you add a host, Pods pins its host key and refuses to connect when it changes.
- **Secrets only travel on stdin.** Secret values are piped to lpod and never end up in command arguments, logs or the database.
- **Reads are cached for a few seconds** per host (`PODS_CACHE_TTL`). Actions such as start, stop or prune clear the cache, and the pages poll for the new state.

See [docs/architecture.md](docs/architecture.md) for the details.

## Features

- **Environments**: add local and remote hosts, with host key pinning and a contract check
- **Overview**: host stats (CPU, memory, disk, containers), failed units and recent activity
- **Applications**: list, start, stop, restart, redeploy and remove; containers, Quadlet files, logs and secrets per application; install from Quadlet files or a Git repository, with a check before installing
- **Containers** and **pods**: list, inspect, logs, stats, start/stop/restart and pruning stopped containers
- **Images**: pull, remove, prune and check for updates
- **Volumes** and **networks**: list, remove and prune
- **Secrets**: create, update and delete, and add them to the applications that need them
- **Journal**: the host's journal, filtered by priority and time
- **Settings per host**: `podman auto-update` (schedule and rollback) and lingering

Pods targets **Podman 6+** with a current systemd. Docker and Podman Compose aren't supported.

## Requirements

On each host:

- Podman 6 or newer and systemd
- [lpod](https://github.com/foxws/lpod) 4 in the user's `PATH` (Pods also looks in `~/.local/bin`), with `jq` installed
- An SSH server, and lingering for rootless users (`loginctl enable-linger`) so their systemd user manager runs without a login

For Pods itself: PHP 8.4+, Node with pnpm, and `ssh` and `ssh-keygen` on the machine or in the container that runs it.

## Getting started

```bash
composer setup     # install, .env, key, migrate, pnpm install and build
php artisan db:seed
composer dev
```

The seeder creates `administrator@example.com` with the password `password`. Pods has a single owner for now; use `php artisan users:create` instead of the seeder outside of development.

Connect the local host:

1. Set `PODS_LOCAL_SSH_USER` in `.env` to the host user that owns the Quadlets. `PODS_LOCAL_SSH_HOST` defaults to `host.containers.internal`.
2. Run `php artisan pods:install`. It generates the SSH key (in `storage/app/private/ssh`), prints the `authorized_keys` line, registers the local environment and checks it.
3. Add that line to `~/.ssh/authorized_keys` on the host, then run `php artisan pods:verify` if the first check failed.

Add remote hosts from **Environments** in the UI, with the same public key.

The development Quadlets in `podman/development/` run Pods itself (Octane, queue, scheduler, Reverb, Vite, Valkey and Mailpit) under Podman.

## Backups

Pods doesn't back up anything yet. Until it does, back up your volumes on the host, for example:

```bash
systemctl --user stop immich.service        # stop the application for a consistent copy
podman volume export immich-library --output immich-library.tar
systemctl --user start immich.service
```

`podman volume export` only covers named volumes. Copy bind mounts (such as `/var/mnt/photos`) with your usual tools. Databases are safer to back up with their own dump tool.

Volume backups in Pods are planned: export a volume through a queued job and get a notification when the archive is ready to download. See [docs/roadmap.md](docs/roadmap.md#volume-backups).

## Structure

```text
src/
├── Domain/        # Environments, Applications, Containers, Pods, Images, Volumes, Networks, Quadlets, Secrets, Users
├── Foundation/    # Providers, middleware, settings and helpers
├── Integrations/
│   ├── Lpod/      # LpodClient: typed methods over the lpod JSON contract
│   └── Ssh/       # SshHostRunner, SshIdentity, host key scanning, FakeHostRunner for tests
├── Modules/
│   ├── Api/       # Versioned API controllers and resources
│   └── Web/       # Inertia controllers, one module per page
└── Support/       # Framework glue: CSP presets, Inertia middleware
```

Pages live in `routes/web.php` and actions in `routes/actions.php`. Everything about a host sits under `/environments/{environment}/…`, so different tabs can stay on different hosts.

## Built with

Laravel 13 on Octane with FrankenPHP, Inertia v3, Vue 3, Nuxt UI 4 and Tailwind CSS 4 (built with Vite+), Fortify (two-factor authentication and passkeys), Reverb with Echo, spatie/laravel-data with TypeScript types, Wayfinder, and [foxws/laravel-ddd](https://github.com/foxws/laravel-ddd). Pods started from [foxws/vue-starter-kit](https://github.com/foxws/vue-starter-kit).

## Commands

```bash
composer dev               # Octane, queue, Reverb, logs and Vite
php artisan pods:install   # Generate the SSH key and register the local host
php artisan pods:verify    # Check that every host answers with a supported lpod contract
php artisan users:create   # Create a user (--password-stdin keeps the password out of argv)
composer test              # Pint check and Pest
composer types:check       # Larastan
composer types:generate    # Write TypeScript types for #[TypeScript] classes and enums
pnpm run check:fix         # Format and lint JS, TS and Vue
pnpm run types:check       # vue-tsc
```

## Testing

Tests never connect to a host. `fakeLpod()` in `tests/Pest.php` swaps the SSH runner for a `FakeHostRunner` that answers with the contract fixtures and records which lpod commands ran.

`php artisan test` runs the unit, feature and browser suites. Browser tests use [Pest's browser plugin](https://pestphp.com/docs/browser-testing) and need Chromium:

```bash
npx playwright install --with-deps chromium
php artisan test --testsuite=Browser
```

## Roadmap

Volume backups, invited users with roles per environment, streaming logs, an application catalog, and shipping Pods as socket-activated Quadlets. See [docs/roadmap.md](docs/roadmap.md).

## License

The MIT License (MIT). See [LICENSE](LICENSE) for more information.
