# Roadmap

Pods is still in development. This is what's planned, roughly in order. Nothing here is a promise, and the order can change.

## Volume backups and restores

Pods doesn't back up or restore volumes yet. Until it does, back them up on the host yourself (see [Backups in the README](../README.md#backups)).

The plan is to export volumes from the UI and import them again, without keeping the browser waiting:

1. **lpod.** `lpod volume export NAME` streams `podman volume export` as a tar archive to stdout. `lpod volume import NAME --stdin` reads one into `podman volume import`, and creates the volume first when it doesn't exist. Both take an option to stop the application's units first and start them again afterwards, for a consistent copy.
2. **Queue.** The Volumes page dispatches a queued job (`ExportVolume` or `ImportVolume`) for one volume or every volume of an application. The job streams the archive over SSH between the host and a Pods disk, so a large volume never sits in memory. It needs a much longer timeout than the 30 seconds that normal lpod calls get.
3. **Notify.** When an export is ready, the job sends the owner a notification (in the app and by mail) with a temporary signed download link. An import, or a failed export or import, sends a notification with the result or lpod's error.
4. **Restore.** An import starts from an archive Pods made, or one the owner uploads. It replaces the volume's contents, so the UI asks for confirmation, shows which units it stops, and offers to export the current contents first.
5. **Retention.** Old archives are pruned on a schedule, with a configurable number of archives kept per volume.

Open questions:

- **Bind-backed volumes.** Some volumes only point at a folder on the host: a `.volume` unit with `Type=none`, `Options=bind` and `Device=`, such as stry's `stry-videos` for a media library. `podman volume export` archives the whole folder for those, and an import would overwrite it. lpod should report them (from the volume's driver options), and Pods should leave them out of "export every volume" and refuse to import into them.
- **Plain bind mounts** (such as `Volume=/var/mnt/photos:/photos`) aren't Podman volumes, so `podman volume export` doesn't cover them. They may need a separate `tar` path, or stay out of scope.
- **Databases** are safer to back up with their own dump tool. A per-application hook, such as a `Label=` with a dump and a restore command, could handle that.
- **Where the archives go:** the Pods data volume, or an S3-compatible disk.
- **Scheduled backups**, with the same retention.

## On-demand applications

Done: lpod's `APP ondemand enable|disable|status` puts a `.socket`, a `systemd-socket-proxyd` service and an idle timer in front of an application, and the application page has an on-demand switch for it, with the ports, the idle timeout and, for a Laravel application, its workers, idle check and scheduler suggested from its Quadlet files. A sleeping application shows as asleep. Next:

1. **Wake.** Done: Start becomes Wake for a sleeping application. lpod's `up` (and `restart` and `redeploy` while it sleeps) starts the socket's proxy service, the way a request does, because a `StopWhenUnneeded=yes` unit started directly stops again.
2. **From pods.yml.** Done: the on-demand dialog is filled in from the stored manifest's `ondemand:` section when it has one, instead of from the Quadlet files, and everything in it stays editable.

## Proxy

An optional reverse proxy per host, so hostnames reach the right container without each application routing for its neighbours. Today stry's own Caddy (FrankenPHP's `CADDY_EXTRA_CONFIG`, rendered by its `CaddySites` class) forwards `ws.example.com` to Reverb and `s3.example.com` to RustFS. With a Pods proxy, stry only serves stry, and the same works for any application, not just Laravel on FrankenPHP.

Done (round 1): `lpod APP routes [set --stdin|remove] --json` and `lpod proxy routes --json` keep each application's routes in `~/.config/pods-proxy/sites/APP.Caddyfile`, join the proxy to its networks with a `lpod-routes-APP.conf` drop-in, and reload Caddy (or restart the proxy when the networks change). The main route of an on-demand application goes to its socket. pods.yml `routes` maps a role to an upstream, the install page asks for a hostname for each, and the Routes card on the application page edits them. The hostnames the app itself needs (`APP_URL`, `VITE_REVERB_HOST`, `AWS_URL`) still come from its `.env`.

Done (round 2): the Proxy section of a host's settings installs `pods-proxy` and sets its certificates, ports and image (see [Proxy](../README.md#proxy)).

Done (round 3, #46): proxy defaults (`Foundation\Settings\ProxySettings`, Settings, Proxy) pre-fill the Proxy section of a host without a proxy, and their domain is what hostnames are suggested under outside a wildcard site. The DNS token stays per host. The Proxy section keeps resolvers, ports and image under Advanced. `install.sh --proxy` routes Pods' own hostname with `lpod pods routes set`, replacing `sites/pods.Caddyfile` and the `pods.conf` drop-in, so Pods' hostname is edited on the `pods` application's Routes card. Pods stays awake behind the proxy, because its on-demand socket listens on 127.0.0.1, which the proxy can't reach.

Next:

- **A real-host check** of rounds 2 and 3: installing the proxy in each mode from Pods, the root certificate download, and `install.sh --proxy` on a host set up by an older install.sh. So far it's only tested against `fakeLpod()` and stubbed binaries.
- **More DNS providers** for the wildcard mode, each a `--with` line in `containers/runtimes/proxy/Containerfile`, a name in `ProxySettingsData::DnsProviders` and its token variable in lpod.

### Round 2: installing the proxy from Pods

A Proxy section in the host's settings installs `pods-proxy`, and picks its ports, its certificates and how routes are written. Four modes cover the setups people run:

| Mode                                                          | Global options                           | Routes are written as                                             | Suggested hostnames                                                    |
| ------------------------------------------------------------- | ---------------------------------------- | ----------------------------------------------------------------- | ---------------------------------------------------------------------- |
| **Let's Encrypt** (a public domain the internet reaches)      | `email`                                  | a site per hostname                                               | `stry.example.com`, `ws.stry.example.com`                              |
| **Local CA** (`.home.arpa`, `.lan`, `.test`)                  | `local_certs`                            | a site per hostname                                               | `stry.home.arpa`, `ws.stry.home.arpa`                                  |
| **Wildcard through DNS** (a public domain on a LAN-only host) | `email`, `acme_dns PROVIDER {env.TOKEN}` | handlers inside one `*.DOMAIN` site, which `abort`s unknown names | `stry.DOMAIN`, `stry-ws.DOMAIN` (one label, so the wildcard covers it) |
| **Plain HTTP** (another proxy in front handles HTTPS)         | `auto_https off`                         | a site per hostname, over `http://`                               | as the front proxy routes them                                         |

In the wildcard mode, the main Caddyfile and an application's routes look like this:

```caddy
{
	admin unix//config/admin.sock|0600
	email you@example.com
	acme_dns cloudflare {env.CLOUDFLARE_API_TOKEN}
}

*.home.example.com {
	tls {
		resolvers 1.1.1.1
	}

	encode zstd gzip

	import sites/*.caddy

	handle {
		abort
	}
}
```

```caddy
# sites/stry.caddy, written by lpod
@stry host stry.home.example.com stry-ws.home.example.com stry-s3.home.example.com
handle @stry {
	reverse_proxy host.containers.internal:8000 {
		stream_close_delay 5m
	}
}
```

1. **lpod.** Done: lpod reads the proxy's Caddyfile, and writes routes as handlers (`sites/APP.caddy`) when it imports `sites/*.caddy` inside a wildcard site, refusing hostnames outside it or more than one label deep. Done: `lpod proxy settings [set --stdin] --json` writes the main Caddyfile from the mode (email, DNS provider, wildcard domain, resolvers) with the ports, image and DNS token in a `lpod-proxy.conf` drop-in, keeps the token as a Podman secret (`Secret=…,type=env`), and rewrites every application's routes for the layout. A hand-written Caddyfile is read into the form, and kept as a backup when it's replaced. `lpod proxy certificate --json` returns the local CA's root certificate, which the Proxy section offers as a download. There's no `lpod proxy install|remove`: Pods installs `containers/proxy` from its own repository as the application `pods-proxy` (`lpod quadlet install`), which keeps its source like any application's, and it's removed like one.
2. **Through the main upstream.** Done: a route can say `via: main` in pods.yml: it goes to the main route's upstream (the socket while on demand) instead of its own container, for applications that route their own hostnames, as stry does with `CaddySites`. Those routes wake a sleeping application too, and need no network drop-in. Routes that say `via: main` share the main route's matcher, as above. stry sets it on `ws` and `s3`.
3. **WebSockets across reloads.** Done: every `reverse_proxy` lpod writes gets `stream_close_delay 5m`, since lpod reloads Caddy whenever routes change, and WebSocket connections (Reverb, Vite HMR) would drop on each reload.
4. **The admin API** listens on a unix socket (`admin unix//config/admin.sock|0600`) instead of port 2019. Done: lpod reloads through it when the Caddyfile says so (`caddy reload --address unix//config/admin.sock`). The Caddyfiles lpod writes and the Quadlet's default one use it, and the health check checks the socket (or port 2019 when a Caddyfile doesn't say so).
5. **The image.** Done: Pods' CI (`.github/workflows/build-proxy.yml`) publishes `ghcr.io/foxws/pods-proxy`: Caddy built with xcaddy and the DNS plugins people ask for (Cloudflare first), so `AutoUpdate=registry` keeps it current, like `ghcr.io/foxws/pods`. The Let's Encrypt and local CA modes keep `docker.io/library/caddy:2`. A setting takes your own image instead.
6. **Ports.** Done: HTTP and HTTPS default to 80 and 443. Rootless Podman can't bind ports below 1024 unless `net.ipv4.ip_unprivileged_port_start` allows it, so Pods says so and suggests high ports (such as 28080 and 28443) behind the host's main proxy, or the sysctl that allows low ones.
7. **Suggestions** follow the mode (done: the wildcard site that lpod reports, and otherwise the domain of the proxy defaults): Pods suggests hostnames under the proxy's domain instead of `home.arpa`, one label deep (`APP-ROLE.DOMAIN`) in the wildcard mode. Short roles read better there, so stry's become `ws`, `s3`, `vite`, `mail` and `s3-console`.

Open questions:

- **Routes to sleeping services that aren't `via: main`.** These still get a 502, such as Mailpit or the RustFS console opened on their own. Caddy could start the app's socket service on a 502 and retry.
- **The proxy itself** must not sleep, so it's always on, unlike the applications behind it.
- **stry** keeps `Support\Octane\CaddySites` while its `ws` and `s3` routes go `via: main`. It can drop it once every route goes to its own container.

## More of pods.yml

Pods reads the sources, the application name, the secrets and the catalog keys (label, description, author, icon, category, website, repository and notes) of a `pods.yml` today. It keeps the `pods.yml` of each application it installs from Git, with the repository, reference, path and commit, and the application page shows its label, icon and source, and the files changed outside Pods since (see [Applications and Quadlets](architecture.md#applications-and-quadlets)). Next:

1. **Setup steps.** Done: `after_install` lists the commands to run once the units are up, such as `php artisan app:install` and creating the first admin, with inputs the UI asks for (a password goes on stdin, never in the arguments). The application page shows a "Finish setup" card from the stored manifest, runs each step with `lpod container NAME exec`, and remembers how it ended. This replaces what laravel-installer did. Done: `after_update` lists the commands to run after each update, such as `php artisan migrate --force`. They take inputs the same way, and the card asks for them again after every update. They don't run by themselves, since the units are still starting when the update returns.
2. **On-demand.** Done: `ondemand` (ports, idle timeout, idle check, the workers to stop and the scheduler) fills in the on-demand switch. See [On-demand applications](#on-demand-applications).
3. **Backups.** `backups` lists the volumes to leave out (such as bind-backed ones) and a database dump command. See [Volume backups and restores](#volume-backups-and-restores).
4. **Updates.** Done: for an application installed at a tag, the Source card offers the repository's newer tags (`lpod quadlet tags`; pre-releases only after a pre-release). Updating fetches the tag at the same path, checks it, refuses when it needs a secret the host lacks, stops the units, installs with `--replace --start`, and keeps the new source. Files the tag no longer has are removed (`lpod APP files remove`) when they're ones Pods installed, unless you untick it; drop-ins added on the host stay and are named. An application installed from a branch is offered its newest commit instead (`lpod quadlet head`), when that isn't the commit it has. Done: an application installed outside Pods, such as with `podman quadlet install`, can be linked to the Git source its files came from ("Link to Git" on the Source card), so it gets updates too. Linking changes nothing on the host: the files that differ from the source show as changed outside Pods, and "Replace with REF" installs the source's over them (keeping drop-ins), or "Keep these files" accepts them. Next: pick the source from the repository's `pods.yml` in the link dialog, as the install page does, instead of typing its path.
5. **Routes.** Done: `routes` lists the upstreams the [proxy](#proxy) can give a hostname. See [Proxy](#proxy).

## Installing applications from a checkout

Pods installs Quadlets from Git. Development setups, such as stry's `containers/quadlets/development`, build their image from a checkout on the host and mount it into the containers. `lpod quadlet fetch` should read a directory on the host as well as a Git repository, so Pods can install those, and show the build's progress while the first start builds the image.

## Later

- **Inertia prop classes.** Done for the application and container pages (`Modules/Web/*/Props`, `ProvidesInertiaProperties`), and every deferred host read goes through `ReadsFromHost::deferFromHost()`. Move another page's props into a class when it grows logic of its own, as these had.
- **Invited users.** The owner invites users and gives each a role per environment (viewer, operator or admin), using spatie/laravel-permission. Host routes are already scoped per environment and go through policies, so this doesn't need a rewrite.
- **Streaming logs** for applications, containers and the journal, instead of polling.
- **An application catalog.** Started: the Install application page lists the applications of the repositories in `config/catalog.php` (lpod's `quadlet catalog`), each a directory with its own `pods.yml` holding its label, description, author, icon, category, website, source repository and notes. [foxws/quadlets](https://github.com/foxws/quadlets) has services like lerd.sh's (PostgreSQL, MariaDB, MySQL, Redis, Valkey, Memcached, Meilisearch, Typesense, RustFS, Mailpit) with production and development sources, and homelab applications from francoism90/dotfiles (Jellyfin, Immich, Vaultwarden and more). Next: brand logos, and more of lerd's services.
- **Application metadata.** The application page shows the label, description and icon of the stored `pods.yml`. Next: the same on the Applications list.
- **Packaging.** `install.sh` installs Pods as plain Quadlets (`containers/quadlets/production`) with SQLite on a volume. It's socket-activated like `cockpit.socket` (lpod's `pods ondemand enable --listen 127.0.0.1`), except with `--proxy`, where the proxy reaches Pods by name and would never wake it. `--no-pods` only prepares another host or user for it (lpod and Pods' key). Next: an idle Pods behind the proxy (such as a route through the socket that doesn't publish port 9090 on the network), and pinned releases of Pods and lpod instead of `main`.
- **Show the commands.** Each action can show the `lpod …` commands it runs, with a copy button, so they can be run on the host by hand. Multi-step flows (install, update) show the commands in order, and a secret or a password shows as a placeholder on stdin, never its value.
- **Labels on resources.** Tags, a color and a description for applications, containers, pods, images, volumes, networks and secrets, to filter the lists by. These live on the host, so Pods keeps them in a table of its own keyed by environment, kind and name (not a container's ID, which changes when systemd recreates it), tagged with spatie/laravel-tags, and forgets the rows of resources that are gone, like `pods:sync`. A description of its own shows instead of the one in `pods.yml`.
- **A terminal.** A shell in a container (`podman exec -it`) or on the host, in the browser with xterm.js. lpod's JSON contract doesn't fit a two-way stream, and Octane workers shouldn't hold one open, so this would be a package of its own: a small bridge between a WebSocket and a PTY over SSH, run as its own Quadlet, that Pods hands a short-lived signed token per session. It needs the invited users' roles first (only an admin of an environment gets a shell), an idle timeout, and a log of who opened which shell.
- **Compose to Quadlet.** A converter, possibly AI-assisted. Pods itself stays Quadlet-only: no Docker and no Podman Compose.
