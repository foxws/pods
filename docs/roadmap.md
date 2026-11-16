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

An application that starts on its first request and sleeps when nobody uses it, like `cockpit.socket`, as a switch on its page instead of a set of files to copy. [stry](https://github.com/francoism90/stry/tree/main/containers/ondemand) ships this as plain units today: a `.socket` and a `systemd-socket-proxyd` service in front of the app, an idle timer, and Quadlet drop-ins that move the app to a local port and set `StopWhenUnneeded=yes`.

1. **lpod.** `lpod quadlet install --stdin` accepts `.socket`, `.timer` and `.service` files (installed into the systemd user directory) and drop-ins (`NAME.container.d/*.conf`) next to the Quadlets, and `lpod quadlet fetch` returns the drop-in folders of a directory too. `lpod APP ondemand enable|disable --json` then enables the socket, stops the app and starts listening, or the other way round, and `lpod apps --json` reports whether an application is on demand and asleep.
2. **Pods.** The application page gets an on-demand switch, and shows a sleeping application as asleep, not stopped. Start becomes "wake" for those: it starts the socket's service, because starting a `StopWhenUnneeded=yes` unit directly stops it again.
3. **Idle check.** Whether an application has work left (queued jobs, running queries) is something only the app knows. The application declares a command for it in its [`pods.yml`](../README.md#application-manifest-podsyml) (`ondemand.idle_check`, for example `php artisan app:idle` in the Horizon container), and the idle timer stops the workers once it succeeds.

Open questions:

- Generating the socket, proxy and idle units from labels on the app's container, so an application only declares its port and idle timeout.
- Native socket activation (FrankenPHP or Caddy binding the socket's file descriptor) instead of `systemd-socket-proxyd`.

## Proxy

An optional reverse proxy per host, so hostnames reach the right container without each application routing for its neighbours. Today stry's own Caddy (FrankenPHP's `CADDY_EXTRA_CONFIG`, rendered by its `CaddySites` class) forwards `ws.example.com` to Reverb and `s3.example.com` to RustFS. With a Pods proxy, stry only serves stry, and the same works for any application, not just Laravel on FrankenPHP.

1. **The proxy.** A Caddy Quadlet per host and user (`pods-proxy`, in `containers/proxy/`; see [Proxy](../README.md#proxy) for installing it by hand today), installed from the host's settings in Pods. It takes ports 80 and 443 and handles HTTPS in one place: an internal CA for local names such as `.home.arpa` (with a download for the root certificate), Let's Encrypt for public domains. The router or NAS in front then needs a single entry for the host.
2. **Networks.** The proxy reaches containers by name (`systemd-stry-reverb:6001`), so it joins the network of every application it routes for, through a drop-in (`pods-proxy.container.d/stry.conf` with `Network=stry.network`). Adding the first route of an application adds the drop-in and restarts the proxy; later route changes only reload it.
3. **lpod.** `lpod proxy install|remove --json`, `lpod proxy routes --json`, and `lpod proxy route add HOST UPSTREAM [--application APP] --json` / `route remove HOST --json`. lpod keeps the routes in one Caddyfile per application next to the proxy's Quadlet and reloads Caddy through its admin endpoint inside the container, so open connections stay up.
4. **pods.yml.** `routes` maps a role to an upstream (`app: systemd-stry:8000`, `websockets: systemd-stry-reverb:6001`, `s3: systemd-stry-rustfs:9000`). Pods asks for the hostname of each when installing, with a suggestion from the application name (`ws.stry.home.arpa`), the same way it asks for secrets, and shows them on the application page to edit later. The hostnames the app itself needs (`APP_URL`, `VITE_REVERB_HOST`, `AWS_URL`) still come from its `.env`.
5. **Without Pods.** Applications document a Caddyfile for your own proxy, so installing by hand keeps working.

Open questions:

- **Sleeping applications.** A route straight to `systemd-stry-reverb:6001` gets a 502 while an on-demand app sleeps, because its services sleep with it. Today the app's own Caddy sits behind the on-demand socket, so any hostname wakes it. Most traffic to Reverb or S3 comes from a page the app just served, so the app is awake then, but opening Mailpit or the RustFS console on its own wouldn't be. Options: send the app's main hostname through its socket (`127.0.0.1:8000`) and the others straight to their containers; let a route say `wake: true` to go through the socket, with the app forwarding it; or have Caddy start the app's socket service on a 502 and retry.
- **The proxy itself** must not sleep, so it's always on, unlike the applications behind it.
- **Pods' own hostname.** `install.sh --proxy --host NAME` routes Pods itself through the proxy.
- **stry** keeps `Support\Octane\CaddySites` until the proxy exists, then drops it in the same change that adds `routes` to its `pods.yml`.

## More of pods.yml

Pods reads the sources, the application name and the secrets of a repository's `pods.yml` today. Next:

1. **Setup steps.** `after_install` lists the commands to run once the units are up, such as `php artisan app:install` and creating the first admin, with inputs the UI asks for (a password goes on stdin, never in the arguments). lpod needs `lpod container NAME exec --stdin --json -- COMMAND`, and Pods needs to remember the manifest of an installed application (a table keyed by environment and application, with the repository, tag and commit it came from), so the application page can show a "Finish setup" panel. This replaces what laravel-installer did.
2. **On-demand.** `ondemand` (port, idle timeout, idle check and the workers to stop) lets lpod write the socket, proxy and idle units, so an application doesn't ship them itself. See [On-demand applications](#on-demand-applications).
3. **Backups.** `backups` lists the volumes to leave out (such as bind-backed ones) and a database dump command. See [Volume backups and restores](#volume-backups-and-restores).
4. **Updates.** With the stored source, the application page can offer "Update to tag X" and reinstall from it with `--replace`.
5. **Routes.** `routes` lists the upstreams the [proxy](#proxy) should give a hostname.

## Installing applications from a checkout

Pods installs Quadlets from Git. Development setups, such as stry's `containers/quadlets/development`, build their image from a checkout on the host and mount it into the containers. `lpod quadlet fetch` should read a directory on the host as well as a Git repository, so Pods can install those, and show the build's progress while the first start builds the image.

## Later

- **Invited users.** The owner invites users and gives each a role per environment (viewer, operator or admin), using spatie/laravel-permission. Host routes are already scoped per environment and go through policies, so this doesn't need a rewrite.
- **Streaming logs** for applications, containers and the journal, instead of polling.
- **An application catalog** of repositories with a `pods.yml`, installable in a few clicks.
- **Application metadata.** The name and description from `pods.yml` (and a URL and icon) shown next to each installed application.
- **Packaging.** `install.sh` installs Pods as plain Quadlets (`containers/quadlets/production`) with SQLite on a volume. Next: socket activation (like `cockpit.socket`), so Pods sleeps when nobody uses it, and pinned releases of Pods and lpod instead of `main`.
- **Compose to Quadlet.** A converter, possibly AI-assisted. Pods itself stays Quadlet-only: no Docker and no Podman Compose.
