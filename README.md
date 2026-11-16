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
- **Applications**: list, start, stop, restart, redeploy and remove; containers, Quadlet files, logs and secrets per application; install from Quadlet files or a Git repository, with a check before installing, reading the repository's [`pods.yml`](#application-manifest-podsyml) when it has one
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

## Install

Pods runs on a host as rootless Podman Quadlets, for the user whose applications it manages. One command installs it, and running it again updates it:

```bash
curl -fsSL https://raw.githubusercontent.com/foxws/pods/main/install.sh | bash
```

It checks Podman, systemd, lingering and SSH, installs lpod, installs Pods' units from `containers/quadlets/production` and its image from `ghcr.io/foxws/pods`, adds Pods' SSH key to your `~/.ssh/authorized_keys` so the host shows up as the local environment, and prints a one-time setup code. Open Pods, which sends you to its setup page, and enter the code to create the owner (or pass `--name`, `--email` and `PODS_OWNER_PASSWORD` to create the owner from the terminal instead). Pods listens on `127.0.0.1:9090`: open it on the host, or through an SSH tunnel (`ssh -L 9090:127.0.0.1:9090 HOST`).

To reach it by a hostname, add the [proxy](#proxy):

```bash
curl -fsSL https://raw.githubusercontent.com/foxws/pods/main/install.sh | bash -s -- --proxy --host pods.example.com
```

On a host shared with other users, give the proxy other ports with `--proxy-ports 28080,28443`, and add `--behind-proxy` when another proxy in front handles HTTPS. `--uninstall` removes Pods and keeps its data; add `--purge` to remove that too. `--help` lists every option.

## Getting started (development)

```bash
composer setup     # install, .env, key, migrate and seed the roles, pnpm install and build
php artisan users:create --super-admin
composer dev
```

`users:create --super-admin` asks for the owner's name, email address and password. Or skip it: while Pods has no owner, it sends you to `/setup`, which asks for the code `php artisan pods:setup` prints. Pods has a single owner for now.

Connect the local host:

1. Set `PODS_LOCAL_SSH_USER` in `.env` to the host user that owns the Quadlets. `PODS_LOCAL_SSH_HOST` defaults to `host.containers.internal`.
2. Run `php artisan pods:install`. It generates the SSH key (in `storage/app/private/ssh`), prints the `authorized_keys` line, registers the local environment and checks it.
3. Add that line to `~/.ssh/authorized_keys` on the host, then run `php artisan pods:verify` if the first check failed.

Add remote hosts from **Environments** in the UI, with the same public key.

The development Quadlets in `podman/development/` run Pods itself (Octane, queue, scheduler, Reverb, Vite, Valkey and Mailpit) under Podman.

## Application manifest (pods.yml)

A repository can tell Pods how to install it with a `pods.yml` in its root. When you add an application from Git, Pods reads it to offer the repository's Quadlet directories, fill in the application name, and explain and fill in the secrets the units need. It never changes the Quadlet files: they stay installable with plain `podman quadlet install`, and a repository without `pods.yml` installs as before.

```yaml
version: 1 # Pods ignores manifests of a version it doesn't know
name: stry # the default application name
description: Self-hosted video library

sources: # Quadlet directories to choose from, relative to the root
    production:
        path: containers/quadlets/production
        description: The published image

secrets: # how to ask for the secrets the units use
    stry-env:
        description: Your .env file
        format: dotenv # a text area; other secrets can read keys from it
    stry-pgsql-password:
        from: stry-env # filled in from DB_PASSWORD in the .env above
        key: DB_PASSWORD
    stry-app-key:
        generate: true # filled in with a random value
```

Every key is optional. Secret values stay in the browser until you install, and go to the host on stdin like any other secret. A manifest Pods can't read is shown as a warning and ignored, so the files can still be installed.

[stry's pods.yml](https://github.com/francoism90/stry/blob/main/pods.yml) is a complete example. Setup commands after installing (such as `php artisan app:install`), on-demand services and backup hints are planned; see the [roadmap](docs/roadmap.md).

## Proxy

`containers/proxy/` is an optional reverse proxy for a host: Caddy, as plain Quadlet files. It routes hostnames to the containers of the applications on that host, so the applications only need `ExposeHostPort=` and never clash over host ports. Install it like any application, from this repository with the directory `containers/proxy` and the name `pods-proxy`, or by hand:

```bash
podman quadlet install --application=pods-proxy containers/proxy
mkdir -p ~/.config/containers/systemd/pods-proxy.container.d
printf '[Container]\nPublishPort=80:80\nPublishPort=443:443\nPublishPort=443:443/udp\n' > ~/.config/containers/systemd/pods-proxy.container.d/ports.conf
systemctl --user daemon-reload
systemctl --user start pods-proxy
```

The first start writes `~/.config/pods-proxy/Caddyfile`, which loads every file in `~/.config/pods-proxy/sites/`. To route an application:

1. Copy its routes to `~/.config/pods-proxy/sites/`, for example `stry.Caddyfile` from stry's `containers/proxy/`.
2. Let the proxy join the application's network, and give it the hostnames, with a drop-in such as `~/.config/containers/systemd/pods-proxy.container.d/stry.conf`:

    ```ini
    [Container]
    Network=stry.network
    Environment=STRY_HOST=stry.example.com
    ```

3. Run `systemctl --user daemon-reload` and `systemctl --user restart pods-proxy`. Only route changes? `podman exec systemd-pods-proxy caddy reload --config /etc/caddy/Caddyfile` applies them without a restart.

The unit publishes no host ports itself, so pick them with a drop-in, `~/.config/containers/systemd/pods-proxy.container.d/ports.conf`: `PublishPort=80:80`, `PublishPort=443:443` and `PublishPort=443:443/udp` (`install.sh --proxy` writes this for you). It gets certificates from Let's Encrypt. On a host shared with other users, each user runs their own proxy on other ports, such as `PublishPort=28080:80` and `PublishPort=28443:443`, and the host's main proxy forwards those users' hostnames to them. For local names, add `cert_issuer internal` (or `local_certs`) to the global options in the Caddyfile; Caddy's root certificate is then in the `pods-proxy-data` volume.

Pods doesn't manage the proxy yet: installing it from the settings of a host, and adding routes from an application's `pods.yml`, are on the [roadmap](docs/roadmap.md#proxy).

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
