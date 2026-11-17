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

1. **The proxy.** A Caddy Quadlet per host and user (`pods-proxy`, in `containers/proxy/`; see [Proxy](../README.md#proxy) for installing it by hand today), installed from the host's settings in Pods. It takes ports 80 and 443 and handles HTTPS in one place: an internal CA for local names such as `.home.arpa` (with a download for the root certificate), Let's Encrypt for public domains. The router or NAS in front then needs a single entry for the host.
2. **Networks.** The proxy reaches containers by name (`systemd-stry-reverb:6001`), so it joins the network of every application it routes for, through a drop-in (`pods-proxy.container.d/stry.conf` with `Network=stry.network`). Adding the first route of an application adds the drop-in and restarts the proxy; later route changes only reload it.
3. **lpod.** `lpod proxy install|remove --json`, `lpod proxy routes --json`, and `lpod proxy route add HOST UPSTREAM [--application APP] --json` / `route remove HOST --json`. lpod keeps the routes in one Caddyfile per application next to the proxy's Quadlet and reloads Caddy through its admin endpoint inside the container, so open connections stay up.
4. **pods.yml.** `routes` maps a role to an upstream (`app: systemd-stry:8000`, `websockets: systemd-stry-reverb:6001`, `s3: systemd-stry-rustfs:9000`). Pods asks for the hostname of each when installing, with a suggestion from the application name (`ws.stry.home.arpa`), the same way it asks for secrets, and shows them on the application page to edit later. The hostnames the app itself needs (`APP_URL`, `VITE_REVERB_HOST`, `AWS_URL`) still come from its `.env`.
5. **Without Pods.** Applications document a Caddyfile for your own proxy, so installing by hand keeps working.

Open questions:

- **Sleeping applications.** A route straight to `systemd-stry-reverb:6001` gets a 502 while an on-demand app sleeps, because its services sleep with it. The plan is to send the app's main hostname to its on-demand socket (`host.containers.internal:PORT` from the proxy container), so a request wakes it. Most traffic to Reverb or S3 comes from a page the app just served, so the app is awake then, but opening Mailpit or the RustFS console on its own wouldn't be. Options for those: let a route say `wake: true` to go through the socket, with the app forwarding it, or have Caddy start the app's socket service on a 502 and retry.
- **The proxy itself** must not sleep, so it's always on, unlike the applications behind it.
- **Pods' own hostname.** `install.sh --proxy --host NAME` routes Pods itself through the proxy.
- **stry** keeps `Support\Octane\CaddySites` until the proxy exists, then drops it in the same change that adds `routes` to its `pods.yml`.

## More of pods.yml

Pods reads the sources, the application name, the secrets and the catalog keys (label, description, author, icon, category, website, repository and notes) of a `pods.yml` today. It keeps the `pods.yml` of each application it installs from Git, with the repository, reference, path and commit, and the application page shows its label, icon and source, and the files changed outside Pods since (see [Applications and Quadlets](architecture.md#applications-and-quadlets)). Next:

1. **Setup steps.** Done: `after_install` lists the commands to run once the units are up, such as `php artisan app:install` and creating the first admin, with inputs the UI asks for (a password goes on stdin, never in the arguments). The application page shows a "Finish setup" card from the stored manifest, runs each step with `lpod container NAME exec`, and remembers how it ended. This replaces what laravel-installer did.
2. **On-demand.** Done: `ondemand` (ports, idle timeout, idle check, the workers to stop and the scheduler) fills in the on-demand switch. See [On-demand applications](#on-demand-applications).
3. **Backups.** `backups` lists the volumes to leave out (such as bind-backed ones) and a database dump command. See [Volume backups and restores](#volume-backups-and-restores).
4. **Updates.** With the stored source, the application page can offer "Update to tag X" and reinstall from it with `--replace`.
5. **Routes.** `routes` lists the upstreams the [proxy](#proxy) should give a hostname.

## Installing applications from a checkout

Pods installs Quadlets from Git. Development setups, such as stry's `containers/quadlets/development`, build their image from a checkout on the host and mount it into the containers. `lpod quadlet fetch` should read a directory on the host as well as a Git repository, so Pods can install those, and show the build's progress while the first start builds the image.

## Later

- **Invited users.** The owner invites users and gives each a role per environment (viewer, operator or admin), using spatie/laravel-permission. Host routes are already scoped per environment and go through policies, so this doesn't need a rewrite.
- **Streaming logs** for applications, containers and the journal, instead of polling.
- **An application catalog.** Started: the Install application page lists the applications of the repositories in `config/catalog.php` (lpod's `quadlet catalog`), each a directory with its own `pods.yml` holding its label, description, author, icon, category, website, source repository and notes. [foxws/quadlets](https://github.com/foxws/quadlets) has services like lerd.sh's (PostgreSQL, MariaDB, MySQL, Redis, Valkey, Memcached, Meilisearch, Typesense, RustFS, Mailpit) with production and development sources, and homelab applications from francoism90/dotfiles (Jellyfin, Immich, Vaultwarden and more). Next: brand logos, and more of lerd's services.
- **Application metadata.** The application page shows the label, description and icon of the stored `pods.yml`. Next: the same on the Applications list.
- **Packaging.** `install.sh` installs Pods as plain Quadlets (`containers/quadlets/production`) with SQLite on a volume. It's socket-activated like `cockpit.socket` (lpod's `pods ondemand enable --listen 127.0.0.1`), except with `--proxy`, where the proxy reaches Pods by name and would never wake it. Next: an idle Pods behind the proxy (such as a route through the socket that doesn't publish port 9090 on the network), and pinned releases of Pods and lpod instead of `main`.
- **Compose to Quadlet.** A converter, possibly AI-assisted. Pods itself stays Quadlet-only: no Docker and no Podman Compose.
