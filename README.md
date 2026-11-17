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
- [lpod](https://github.com/foxws/lpod) 4 in the user's `PATH` (Pods also looks in `~/.local/bin`), with `jq` installed, and `git` for installing and updating applications from Git repositories and the catalog
- An SSH server, and lingering for rootless users (`loginctl enable-linger`) so their systemd user manager runs without a login

For Pods itself: PHP 8.4+, Node with pnpm, and `ssh` and `ssh-keygen` on the machine or in the container that runs it.

## Install

Pods runs on a host as rootless Podman Quadlets, for the user whose applications it manages. One command installs it, and running it again updates it:

```bash
curl -fsSL https://raw.githubusercontent.com/foxws/pods/main/install.sh | bash
```

It checks Podman, systemd, lingering and SSH, installs lpod, installs Pods' units from `containers/quadlets/production` and its image from `ghcr.io/foxws/pods`, adds Pods' SSH key to your `~/.ssh/authorized_keys` so the host shows up as the local environment, and prints a one-time setup code. Open Pods, which sends you to its setup page, and enter the code to create the owner (or pass `--name`, `--email` and `PODS_OWNER_PASSWORD` to create the owner from the terminal instead). Pods listens on `127.0.0.1:9090`: open it on the host, or through an SSH tunnel (`ssh -L 9090:127.0.0.1:9090 HOST`). Like `cockpit.socket`, a socket listens there and starts Pods on the first request, and Pods sleeps again after 10 minutes without one. Pass `--no-idle` to keep it running all the time. With `--proxy`, Pods runs all the time for now.

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

### Running Pods under Podman

The units in `containers/quadlets/development` run Pods itself from a checkout in `~/projects/pods` (Octane, the queue worker, the scheduler, Reverb, Vite, Valkey and Mailpit). They're named `pods-dev`, so they can run next to an installed Pods. Change the path in `pods-dev.build` and `pods-dev-source.volume` if your checkout is elsewhere.

```bash
podman quadlet install --application=pods-dev containers/quadlets/development
systemctl --user start pods-dev
```

The first start builds the image, which takes a while. The units publish no ports. Reach them through the Pods proxy: copy `containers/proxy/sites/pods-dev.Caddyfile` to `~/.config/pods-proxy/sites/`, and `containers/proxy/pods-proxy.container.d/pods-dev.conf` to `~/.config/containers/systemd/pods-proxy.container.d/` with your hostnames. Then set `APP_URL`, `VITE_HMR_HOST` and `REVERB_HOST` (with `REVERB_PORT=443` and `REVERB_SCHEME=https`) in `.env` to match, and restart the proxy. Without the proxy, publish the app with a drop-in instead (see the comment in `pods-dev.container`). Vite's hot reload then won't reach the browser.

## Application manifest (pods.yml)

A repository can tell Pods how to install it with a `pods.yml` in its root, or in a directory per application. When you add an application from Git, Pods reads it to offer the repository's Quadlet directories, fill in the application name, and explain and fill in the secrets the units need. It never changes the Quadlet files: they stay installable with plain `podman quadlet install`, and a repository without `pods.yml` installs as before.

```yaml
version: 1 # Pods ignores manifests of a version it doesn't know
name: stry # the default application name
label: stry # shown in the catalog, like the keys below
description: Self-hosted video library
author: francoism90
icon: i-lucide-film # a Lucide icon
category: Media
website: https://github.com/francoism90/stry
repository: https://github.com/francoism90/stry # its source code
notes: Shown before installing.

sources: # Quadlet directories to choose from, relative to this pods.yml
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

after_install: # commands to run once the units are up, from the application page
    install:
        label: Install stry
        container: stry # the container's Quadlet, without .container
        user: docker # optional, like workdir
        command: php artisan app:install --no-interaction # split on whitespace
    admin:
        label: Create the first admin
        container: stry
        command: php artisan users:create --super-admin --password-stdin --no-interaction
        inputs: # asked for before the step runs
            name:
                option: --name
            email:
                type: email # text (the default), email or password
                option: --email # passed as --email=VALUE
            password:
                type: password # an input without an option goes on stdin

routes: # upstreams the Pods proxy can give a hostname; Pods asks for the hostnames
    app:
        upstream: systemd-stry:8000 # a container and its port
        description: The web app # the first route is the main one, unless another says main: true
    ws:
        upstream: systemd-stry-reverb:6001
        via: main # optional: go where the main route goes, for an app that routes its own hostnames

ondemand: # fills in the on-demand switch; the keys match lpod's ondemand status
    port: 8000 # the socket on the host
    app_port: 8000 # inside the app container, read from its unit when left out
    idle_timeout: 10min
    workers: [stry-horizon] # keep running until the idle check says there's no work
    idle_check:
        container: stry-horizon
        user: docker
        command: php /app/artisan app:idle
    schedule: # runs schedule:run only at these minutes, in UTC
        container: stry-schedule
        at: ['*:00', '01:00']
        environment:
            APP_COMMAND: php /app/artisan schedule:run
```

Every key is optional. When you fetch a directory, Pods reads the nearest `pods.yml`: the one in that directory, or else in a directory above it. Secret values stay in the browser until you install, and go to the host on stdin like any other secret. A step's inputs aren't stored; at most one input goes on stdin, and a password always does, so it never shows in the arguments. A manifest Pods can't read is shown as a warning and ignored, so the files can still be installed.

[stry's pods.yml](https://github.com/francoism90/stry/blob/main/pods.yml) is a complete example. Backup hints and routes are planned; see the [roadmap](docs/roadmap.md).

### Catalog

The Install application page lists the applications of the repositories in `config/catalog.php`, [foxws/quadlets](https://github.com/foxws/quadlets) by default. Every directory at such a repository's root with a `pods.yml` is one application, such as `mariadb/pods.yml` with `production` and `development` sources next to it. The host clones the repository to read them, and Pods keeps the list for a few minutes.

## Proxy

`containers/proxy/` is an optional reverse proxy for a host: Caddy, as plain Quadlet files. It routes hostnames to the containers of the applications on that host, so the applications only need `ExposeHostPort=` and never clash over host ports.

Install it from the Proxy section of a host's settings in Pods. Pick how it gets certificates, and its ports:

| Certificates                | For                                                                                                                                                                                | Hostnames look like                       |
| --------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------- |
| Let's Encrypt               | A public domain the internet reaches on port 80 or 443                                                                                                                             | `stry.example.com`, `ws.stry.example.com` |
| Local certificate authority | Names such as `.home.arpa`, `.lan` or `.test`. Download the root certificate from the Proxy section, and trust it once on each device                                              | `stry.home.arpa`, `ws.stry.home.arpa`     |
| Wildcard through DNS        | A public domain on a host only your network reaches: one certificate for `*.DOMAIN` through your DNS provider (Cloudflare), whose API token is kept as a Podman secret on the host | `stry.DOMAIN`, `stry-ws.DOMAIN`           |
| Plain HTTP                  | Another proxy in front that handles HTTPS                                                                                                                                          | as the front proxy routes them            |

Pods installs `containers/proxy` from this repository as the application `pods-proxy`, and lpod writes `~/.config/pods-proxy/Caddyfile` and the drop-in `pods-proxy.container.d/lpod-proxy.conf` with the ports, the image and the DNS token (see `lpod proxy settings` in lpod's `docs/commands.md`). The wildcard mode runs `ghcr.io/foxws/pods-proxy:main`, Caddy with the Cloudflare plugin (`containers/runtimes/proxy/Containerfile`, rebuilt every week); the others run `caddy:2`, and any of them can run your own image. Saving replaces a Caddyfile you wrote yourself, and keeps yours next to it as a backup, so keep sites of your own in `~/.config/pods-proxy/sites/*.Caddyfile`. Rootless Podman can't publish ports below `net.ipv4.ip_unprivileged_port_start` (1024 by default), so Pods warns about 80 and 443 there: allow them, or use ports such as 28080 and 28443 behind the host's main proxy, which is also how each user on a shared host runs their own proxy. `install.sh --proxy` installs it too, with Let's Encrypt (or plain HTTP with `--behind-proxy`) on `--proxy-ports`, the first time only, and routes `--host` to Pods as a route of the application `pods`, which you edit on its page like any other.

**Proxy defaults.** Under Settings, Proxy, the owner sets what a host's Proxy section starts from before its proxy is installed (the mode, email, ports, image), so installing it on another host is one click. The domain there is the one Pods suggests hostnames under (`stry.example.com`, `ws.stry.example.com`) instead of `home.arpa`, except behind a wildcard site, whose own domain wins. Hosts with a proxy keep their own settings, and the DNS token is never part of the defaults: it's asked for per host, and only kept there.

Once the proxy is installed, Pods manages the routes: the install page asks for a hostname for each route an application's `pods.yml` names, and the Routes card on the application page adds, changes and removes them. lpod writes them to `~/.config/pods-proxy/sites/`, with a drop-in that joins the application's networks, then reloads Caddy, or restarts the proxy when it joins a new network. The main route of an on-demand application goes to its socket, so a request wakes it.

To install it by hand instead:

```bash
podman quadlet install --application=pods-proxy containers/proxy
echo '{"mode": "local", "http_port": 80, "https_port": 443}' | lpod proxy settings set --stdin --json
systemctl --user start pods-proxy
```

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

Volume backups, invited users with roles per environment, streaming logs, and an application catalog. See [docs/roadmap.md](docs/roadmap.md).

## License

The MIT License (MIT). See [LICENSE](LICENSE) for more information.
