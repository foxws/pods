# Pods

[![Tests](https://github.com/foxws/pods/actions/workflows/tests.yml/badge.svg)](https://github.com/foxws/pods/actions/workflows/tests.yml)
[![Code Quality](https://github.com/foxws/pods/actions/workflows/code-quality.yml/badge.svg)](https://github.com/foxws/pods/actions/workflows/code-quality.yml)
[![License](https://img.shields.io/github/license/foxws/pods)](LICENSE)

A self-hosted web UI for [Podman Quadlet](https://docs.podman.io/en/latest/markdown/podman-systemd.unit.5.html), made for homelabs. Pods manages the applications, containers, pods, images, volumes, networks, secrets and journal of one or more Podman hosts, over SSH.

> [!WARNING]
> **Pods is still in development.** Expect breaking changes, incomplete features and rough edges. The lpod JSON contract, the database schema and the routes can change between commits, and some lpod commands have not been tested on a real Podman host yet. Don't point Pods at a host you can't afford to lose, and **back up your volumes yourself** (see [Backups](#backups)). Pods doesn't do that yet.

## Why Pods

- **Your applications are plain Quadlet files.** A `.container`, `.volume` or `.network` file is a systemd unit that Podman ships with. Pods installs them, starts them and shows what they're doing, but it never adds a format of its own on top. Delete Pods and every application keeps running, managed by systemd as before. You can always edit, install or debug them with `podman quadlet`, `systemctl` and `journalctl`.
- **systemd does the work.** Starting at boot, restarting on failure, logs, timers, dependencies and `podman auto-update` all come from systemd and Podman. Pods adds no daemon or agent of its own on the host. [lpod](https://github.com/foxws/lpod) is a shell script that Pods runs over SSH.
- **Nothing has to keep running.** Applications can sleep until a request comes in, through systemd socket activation (see [On-demand applications](#on-demand-applications)). Pods itself does the same: it's a container that starts when you open it and stops when you leave.
- **One UI for several hosts.** Every host is an SSH target, the one Pods runs on too.

### Compared with

|                                               | What it is                                                                                 | How Pods differs                                                                                                                                                                                                                                     |
| --------------------------------------------- | ------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| [Cockpit](https://cockpit-project.org)        | A web console for a whole Linux server, with a Podman page for containers, pods and images | Pods is only about the applications on a host, and treats them as groups of Quadlet units: install from Git, routes, secrets, setup steps and updates. It runs fine next to Cockpit, and sleeps through a socket the same way `cockpit.socket` does. |
| [lerd](https://lerd.sh)                       | A local Laravel development environment for Linux on rootless Podman                       | lerd is for developing on your own machine. Pods runs applications on your servers and homelab. An application you develop with lerd can ship Quadlet files and a `pods.yml` to be installed with Pods.                                              |
| [Laravel Sail](https://laravel.com/docs/sail) | A Docker Compose development environment for one Laravel project                           | Sail is for development, with Docker and Compose. Pods is for running applications, with Podman and systemd, and isn't tied to Laravel.                                                                                                              |
| [Laravel Cloud](https://cloud.laravel.com)    | Managed hosting for Laravel applications                                                   | Laravel Cloud runs and scales your application for you. Pods runs any containerized application on hardware you own, and you're responsible for the host.                                                                                            |
| [Portainer](https://www.portainer.io)         | A container UI for Docker, Swarm and Kubernetes, built around Compose stacks               | Pods doesn't support Docker or Compose. Its applications are systemd units, so they behave like the rest of the host.                                                                                                                                |

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
- **On demand**: let an application sleep until its first request, through a systemd socket (see [On-demand applications](#on-demand-applications))
- **Timers**: start an application's jobs on a schedule with systemd timers, from its `pods.yml`, and run them by hand
- **Proxy**: an optional Caddy per host that gives applications hostnames and certificates (see [Proxy](#proxy))
- **Settings per host**: `podman auto-update` (schedule and rollback), lingering, and updating lpod to the release Pods needs

Pods targets **Podman 6+ on systemd**. Docker and Podman Compose aren't supported.

## Requirements

On each host:

- Podman 6 or newer, running under systemd. Quadlet is part of Podman, so there's nothing else to install for it. Most major distributions package Podman 6 in their current releases; check with `podman --version`
- [lpod](https://github.com/foxws/lpod) 4 in the user's `PATH` (Pods also looks in `~/.local/bin`), with `jq` installed, and `git` for installing and updating applications from Git repositories and the catalog
- An SSH server, and lingering for rootless users (`loginctl enable-linger`) so their systemd user manager runs without a login

For Pods itself: PHP 8.5+, Node with pnpm, and `ssh` and `ssh-keygen` on the machine or in the container that runs it.

## Install

Pods runs on a host as rootless Podman Quadlets, for the user whose applications it manages. One command installs it, and running it again updates it:

```bash
curl -fsSL https://raw.githubusercontent.com/foxws/pods/v1.0.0-rc.4/install.sh | bash
```

It checks Podman, systemd, lingering and SSH, installs lpod, installs Pods' units from `containers/quadlets/production` and its image from `ghcr.io/foxws/pods`, adds Pods' SSH key to your `~/.ssh/authorized_keys` so the host shows up as the local environment, and prints a one-time setup code. Open Pods, which sends you to its setup page, and enter the code to create the owner (or pass `--name`, `--email` and `PODS_OWNER_PASSWORD` to create the owner from the terminal instead). Pods listens on `127.0.0.1:9393` (`--port` picks another; Cockpit has 9090): open it on the host, or through an SSH tunnel (`ssh -L 9393:127.0.0.1:9393 HOST`). Running it again keeps the port, and updates lpod when it's older than the release needs.

### Pods only runs while you use it

Pods is a container like any of your applications, not a service your host depends on. Your applications are systemd units, so they keep running, restarting and updating whether Pods is up or not.

By default, `install.sh` doesn't keep Pods running. Like `cockpit.socket`, a systemd socket (`pods-ondemand.socket`) listens on `127.0.0.1:9393` and starts Pods on the first request. Pods stops again after 10 minutes without one, and its scheduler only wakes for a moment at 01:00 UTC for the daily housekeeping. While Pods has a route through the [proxy](#proxy), the socket listens on every address instead, and the route goes through it, so a request through the proxy wakes Pods too. You can also start and stop it yourself:

```bash
lpod pods down                                # stop Pods now; the next request starts it again
systemctl --user stop pods-ondemand.socket    # keep it off, even when something connects
systemctl --user start pods-ondemand.socket   # let the next request start it again
```

Pass `--no-idle` to keep Pods running all the time, and start and stop it as `pods.service` instead.

**Behind the proxy**, with `--proxy` or a route set in Pods (even when `install.sh` runs again without `--proxy`), the proxy container reaches the host as `host.containers.internal`, which isn't `127.0.0.1`. So `pods-ondemand.socket` listens on port 9393 on every address, and lpod points Pods' route at it. That port is then reachable from your network too, so check that the host's firewall blocks it (Fedora Server's default firewalld zone only opens the services it lists, but Fedora Workstation's opens every port from 1025 up), or pass `--no-idle` to keep Pods on `127.0.0.1`. A unix socket for the proxy doesn't work instead: SELinux doesn't let a container connect to a socket the user's systemd holds, and a user's socket units can't limit who connects.

### A hostname for Pods

To reach it by a hostname, add the [proxy](#proxy):

```bash
curl -fsSL https://raw.githubusercontent.com/foxws/pods/v1.0.0-rc.4/install.sh | bash -s -- --proxy --host pods.example.com
```

On a host shared with other users, give the proxy other ports with `--proxy-ports 28080,28443`, and add `--behind-proxy` when another proxy in front handles HTTPS. `--uninstall` removes Pods and keeps its data; add `--purge` to remove that too. `--help` lists every option.

To prepare a host (or another user on the same machine) for a Pods that runs elsewhere, use `--no-pods`. It checks the requirements and installs lpod, and `--authorized-key` adds Pods' public key (**Hosts**, **Copy public key**) to that user's `~/.ssh/authorized_keys`. It installs no Pods and no proxy: install the proxy from Pods, in the new environment's settings.

```bash
curl -fsSL https://raw.githubusercontent.com/foxws/pods/v1.0.0-rc.4/install.sh | bash -s -- --no-pods --authorized-key 'ssh-ed25519 AAAA… pods'
```

## Getting started

Once `install.sh` is done:

1. **Create the owner.** Open `http://127.0.0.1:9393` on the host (or `ssh -L 9393:127.0.0.1:9393 HOST` and open it on your own machine). Pods sends you to **Set up Pods**. Enter the setup code `install.sh` printed, then your name, email address and password. If you lost the code, `journalctl --user -u pods` shows it again.
2. **Check the local host.** **Hosts** lists the machine Pods runs on as the local environment, already connected. Its overview shows CPU, memory, disk and any failed units.
3. **Install an application from the catalog.** Open **Applications**, then **Install application**, and search the **Catalog** for Memos. Pick it, check the Quadlet file Pods shows you, keep **Start after installing** on, and press **Install memos**. Memos listens on `127.0.0.1:5230` on the host. Its page has its containers, its journal, and Start, Stop, Restart, and Pull and redeploy.
4. **Add another host (optional).** As the user that owns the Quadlets there, run `install.sh --no-pods --authorized-key '…'` with Pods' public key (**Hosts**, **Copy public key**), or install lpod (see [Requirements](#requirements)) and add the key to `~/.ssh/authorized_keys` yourself. Then use **Add environment** with its hostname and that user. Another user on the machine Pods runs on is `host.containers.internal`, with its own lingering (`sudo loginctl enable-linger USER`), and its proxy on ports such as 28080 behind the main one. Pods pins the host's key on first contact.

Each Pods release needs a matching lpod. When a host has another one, its **Settings** page offers **Update to vX**, which runs `lpod self-update` over SSH as that host's user, since each user has their own lpod. Pods then checks the version the host reports.

Next, give an application a hostname with the [proxy](#proxy), or make your own repository installable, as below.

## Your first application

An application is a group of plain Quadlet files. Pods shows every application Podman knows about (`podman quadlet list`), however it got there:

- **From a Git repository** with Quadlet files, through **Install application**, **Git repository**. This is the only way that stores where the files came from, so Pods can offer updates and setup steps. A `pods.yml` is optional.
- **From Quadlet files you paste** on the same page.
- **From a terminal**, with `podman quadlet install` or `lpod quadlet install`. Pods shows these too, with their containers, logs and Start and Stop, but without a source to update from.

A `pods.yml` next to the files tells Pods what to call the application and which routes it has. This example runs [traefik/whoami](https://github.com/traefik/whoami), a tiny web server that answers with the request it got:

```text
hello/
├── pods.yml
└── quadlets/
    ├── hello.container
    └── hello.network
```

`quadlets/hello.network` gives the application a network of its own, which the proxy joins to reach it by name:

```ini
[Network]
```

`quadlets/hello.container`:

```ini
[Unit]
Description=Hello

[Container]
Image=docker.io/traefik/whoami:latest
AutoUpdate=registry
Network=hello.network
# For the proxy. Without it, publish a port instead: PublishPort=127.0.0.1:8080:80
ExposeHostPort=80

[Service]
Restart=on-failure

[Install]
WantedBy=default.target
```

`pods.yml`:

```yaml
version: 1
name: hello
label: Hello
description: Answers with the request it got
icon: i-lucide-hand

sources:
    production:
        path: quadlets

routes:
    app:
        upstream: systemd-hello:80 # Quadlet names the container systemd-hello
        description: The web page
```

Push it, then in Pods:

1. Open **Install application**, **Git repository**, enter the repository URL and **Fetch repository**. Pods reads `pods.yml`, fills in the name, and shows the two files.
2. Under **Hostnames**, press **Suggest** for a name such as `hello.home.arpa`, or type your own. This needs the [proxy](#proxy) on that host. Without it, leave the hostname empty and use the `PublishPort=` line instead.
3. Press **Install hello**. Open the hostname, and whoami answers. You can change the hostname later on the application's **Routes** card.

The files stay plain Quadlets, so `podman quadlet install --application=hello quadlets` installs the same thing without Pods. Installed from a branch, the application's page offers its newest commit as an update. Installed from a tag, it offers newer tags. For secrets, setup steps and on-demand, see [pods.yml](#application-manifest-podsyml). [foxws/quadlets](https://github.com/foxws/quadlets) has more examples.

## Development

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
name: hello # the default application name
label: hello # shown in the catalog, like the keys below
description: Self-hosted video library
author: Example
icon: i-lucide-film # a Lucide icon
category: Media
website: https://hello.example.com
repository: https://github.com/example/hello # its source code
notes: Shown before installing.

sources: # Quadlet directories to choose from, relative to this pods.yml
    production:
        path: containers/quadlets/production
        description: The published image

secrets: # how to ask for the secrets the units use
    hello-env:
        description: Your .env file
        format: dotenv # a text area; other secrets can read keys from it
    hello-pgsql-password:
        from: hello-env # filled in from DB_PASSWORD in the .env above
        key: DB_PASSWORD
    hello-app-key:
        generate: true # filled in with a random value

after_install: # commands to run once the units are up, from the application page
    install:
        label: Install hello
        container: hello # the container's Quadlet, without .container
        user: docker # optional, like workdir
        command: php artisan app:install --no-interaction # split on whitespace
    admin:
        label: Create the first admin
        container: hello
        command: php artisan users:create --super-admin --password-stdin --no-interaction
        inputs: # asked for before the step runs
            name:
                option: --name
            email:
                type: email # text (the default), email or password
                option: --email # passed as --email=VALUE
            password:
                type: password # an input without an option goes on stdin

after_update: # commands to run after each update, once the new units are up; names differ from after_install's
    migrate:
        label: Migrate the database
        container: hello
        user: docker
        command: php artisan migrate --force --no-interaction

routes: # upstreams the Pods proxy can give a hostname; Pods asks for the hostnames
    app:
        upstream: systemd-hello:8000 # a container and its port
        description: The web app # the first route is the main one, unless another says main: true
    ws:
        upstream: systemd-hello-reverb:6001
        via: main # optional: go where the main route goes, for an app that routes its own hostnames

ondemand: # fills in the on-demand switch; the keys match lpod's ondemand status
    port: 8000 # the socket on the host
    app_port: 8000 # inside the app container, read from its unit when left out
    idle_timeout: 10min
    workers: [hello-horizon] # keep running until the idle check says there's no work
    idle_check:
        container: hello-horizon
        user: docker
        command: php /app/artisan app:idle
    schedule: # runs schedule:run only at these minutes, in UTC
        container: hello-schedule
        at: ['*:00', '01:00']
        environment:
            APP_COMMAND: php /app/artisan schedule:run

timers: # containers a systemd timer starts on a schedule; the keys are the directives they become
    backup: # lpod writes APP-NAME.timer, here hello-backup.timer
        description: Backs up the database
        container: hello-backup # a container Quadlet of the app that runs once and exits (Type=oneshot)
        on_calendar: daily # OnCalendar=, or a list of calendar events
        persistent: true # Persistent=: catch up on a run missed while the host was off
        randomized_delay_sec: 30min # RandomizedDelaySec=
        timeout_start_sec: infinity # TimeoutStartSec= for the container, in a drop-in
```

Every key is optional. When you fetch a directory, Pods reads the nearest `pods.yml`: the one in that directory, or else in a directory above it. Secret values stay in the browser until you install, and go to the host on stdin like any other secret. After an update, the application page asks for the new release's `after_update` steps, which take inputs like setup steps, and only counts a step as done once it ran since that update. A step's inputs aren't stored; at most one input goes on stdin, and a password always does, so it never shows in the arguments. A manifest Pods can't read is shown as a warning and ignored, so the files can still be installed.

Pods sets up the `timers` after installing and after each update, so they follow the release: a timer the new `pods.yml` no longer names is removed. The application page has a Timers card with each timer's schedule, next and last run, and **Run now**. A timer starts what its container `Requires=`, so it also wakes an on-demand application, which sleeps again after its idle timeout. `timers` are for jobs that live on the host; a Laravel scheduler stays in `ondemand.schedule`, since it only runs from a timer while the application is on demand.

The applications in [foxws/quadlets](https://github.com/foxws/quadlets) each have a `pods.yml` to start from. Backup hints are planned; see the [roadmap](docs/roadmap.md).

### Catalog

The Install application page lists the applications of the repositories in `config/catalog.php`, [foxws/quadlets](https://github.com/foxws/quadlets) by default. Every directory at such a repository's root with a `pods.yml` is one application, such as `mariadb/pods.yml` with `production` and `development` sources next to it. The host clones the repository to read them, and Pods keeps the list for a few minutes.

## Proxy

`containers/proxy/` is an optional reverse proxy for a host: Caddy, as plain Quadlet files. It routes hostnames to the containers of the applications on that host, so the applications only need `ExposeHostPort=` and never clash over host ports.

Install it from the Proxy section of a host's settings in Pods. Pick how it gets certificates, and its ports:

| Certificates                | For                                                                                                                                                                                | Hostnames look like                         |
| --------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------- |
| Let's Encrypt               | A public domain the internet reaches on port 80 or 443                                                                                                                             | `hello.example.com`, `ws.hello.example.com` |
| Local certificate authority | Names such as `.home.arpa`, `.lan` or `.test`. Download the root certificate from the Proxy section, and trust it once on each device                                              | `hello.home.arpa`, `ws.hello.home.arpa`     |
| Wildcard through DNS        | A public domain on a host only your network reaches: one certificate for `*.DOMAIN` through your DNS provider (Cloudflare), whose API token is kept as a Podman secret on the host | `hello.DOMAIN`, `hello-ws.DOMAIN`           |
| Plain HTTP                  | Another proxy in front that handles HTTPS                                                                                                                                          | as the front proxy routes them              |

Pods installs `containers/proxy` from this repository as the application `pods-proxy`, and lpod writes `~/.config/pods-proxy/Caddyfile` and the drop-in `pods-proxy.container.d/lpod-proxy.conf` with the ports, the image and the DNS token (see `lpod proxy settings` in lpod's `docs/commands.md`). The wildcard mode runs `ghcr.io/foxws/pods-proxy:main`, Caddy with the Cloudflare plugin (`containers/runtimes/proxy/Containerfile`, rebuilt every week); the others run `caddy:2`, and any of them can run your own image. Saving replaces a Caddyfile you wrote yourself, and keeps yours next to it as a backup, so keep sites of your own in `~/.config/pods-proxy/sites/*.Caddyfile`. Rootless Podman can't publish ports below `net.ipv4.ip_unprivileged_port_start` (1024 by default), so Pods warns about 80 and 443 there: allow them, or use ports such as 28080 and 28443 behind the host's main proxy, which is also how each user on a shared host runs their own proxy. `install.sh --proxy` installs it too, with Let's Encrypt (or plain HTTP with `--behind-proxy`) on `--proxy-ports`, the first time only, and routes `--host` to Pods as a route of the application `pods`, which you edit on its page like any other.

**Proxy defaults.** Under Settings, Proxy, the owner sets what a host's Proxy section starts from before its proxy is installed (the mode, email, ports, image), so installing it on another host is one click. The domain there is the one Pods suggests hostnames under (`hello.example.com`, `ws.hello.example.com`) instead of `home.arpa`, except behind a wildcard site, whose own domain wins. Hosts with a proxy keep their own settings, and the DNS token is never part of the defaults: it's asked for per host, and only kept there.

Once the proxy is installed, Pods manages the routes: the install page asks for a hostname for each route an application's `pods.yml` names, and the Routes card on the application page adds, changes and removes them. lpod writes them to `~/.config/pods-proxy/sites/`, with a drop-in that joins the application's networks, then reloads Caddy, or restarts the proxy when it joins a new network. The main route of an on-demand application goes to its socket, so a request wakes it.

To install it by hand instead:

```bash
podman quadlet install --application=pods-proxy containers/proxy
echo '{"mode": "local", "http_port": 80, "https_port": 443}' | lpod proxy settings set --stdin --json
systemctl --user start pods-proxy
```

## On-demand applications

An application you don't use all day doesn't have to run all day. Turn on **On demand** on its page, and it sleeps until a request comes in. This is systemd socket activation, the way `cockpit.socket` works, so it needs no daemon of Pods' own and keeps working when Pods itself is off. lpod writes the units:

| Unit                                 | What it does                                                                                                                                                 |
| ------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `APP-ondemand.socket`                | Listens on the application's port, and starts the service below on the first connection                                                                      |
| `APP-ondemand.service`               | `systemd-socket-proxyd`: starts the application, forwards the connection to it, and exits after the idle timeout (10 minutes by default) without one         |
| A drop-in for the main unit          | Keeps it from starting at boot, and lets systemd stop it once the proxy is gone (`StopWhenUnneeded=yes`)                                                     |
| `APP-idle.timer`, `APP-idle.service` | Optional: once the application is asleep and an idle command finds no work left, stops its workers (such as a queue worker), so their services can sleep too |
| A timer for the scheduler            | Optional: runs a scheduler container only at the minutes a task is due, instead of every minute                                                              |

The first request after a sleep waits for the application to start, so it's best for applications that start in a few seconds. **Start** becomes **Wake** while an application sleeps, and **Stop** puts it to sleep. Pods suggests the ports, workers and scheduler from the Quadlet files, or from the `ondemand:` section of its [`pods.yml`](#application-manifest-podsyml), and everything stays editable.

**Behind the proxy**, the main route of an on-demand application goes to its socket, so opening its hostname wakes it. Routes with `via: main` go the same way. Other routes, such as an admin UI on a container of its own, get an error while the application sleeps, until it's woken another way. The proxy itself always runs.

The same units work without Pods, from a terminal: `lpod APP ondemand enable --port PORT --app-port PORT` (see `lpod APP ondemand` in lpod's `docs/commands.md`).

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
