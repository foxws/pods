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

- **Bind-backed volumes.** Some volumes only point at a folder on the host: a `.volume` unit with `Type=none`, `Options=bind` and `Device=`, such as a `media.volume` for a media library. `podman volume export` archives the whole folder for those, and an import would overwrite it. lpod should report them (from the volume's driver options), and Pods should leave them out of "export every volume" and refuse to import into them.
- **Plain bind mounts** (such as `Volume=/var/mnt/photos:/photos`) aren't Podman volumes, so `podman volume export` doesn't cover them. They may need a separate `tar` path, or stay out of scope.
- **Databases** are safer to back up with their own dump tool. A per-application hook, such as a `Label=` with a dump and a restore command, could handle that.
- **Where the archives go:** the Pods data volume, or an S3-compatible disk.
- **Scheduled backups**, with the same retention.

## Proxy

Still open for the optional per-host proxy (see [Proxy in the README](../README.md#proxy)):

- **A real-host check:** installing the proxy in each mode from Pods, the root certificate download, and `install.sh --proxy` on a host set up by an older install.sh. So far it's only tested against `fakeLpod()` and stubbed binaries.
- **More DNS providers** for the wildcard mode, each a `--with` line in `containers/runtimes/proxy/Containerfile`, a name in `ProxySettingsData::DnsProviders` and its token variable in lpod.
- **Routes to sleeping services that aren't `via: main`.** These still get a 502, such as Mailpit or the RustFS console opened on their own. Caddy could start the app's socket service on a 502 and retry.

## More of pods.yml

1. **Backups.** `backups` lists the volumes to leave out (such as bind-backed ones) and a database dump command. See [Volume backups and restores](#volume-backups-and-restores).
2. **Linking to Git.** Pick the source from the repository's `pods.yml` in the "Link to Git" dialog, as the install page does, instead of typing its path.
3. **Timers.** Edit a timer's schedule on the application page, kept over updates like a drop-in.

## Installing applications from a checkout

Pods installs Quadlets from Git. Development setups, such as a Laravel application's `containers/quadlets/development`, build their image from a checkout on the host and mount it into the containers. `lpod quadlet fetch` should read a directory on the host as well as a Git repository, so Pods can install those, and show the build's progress while the first start builds the image.

## Later

- **Invited users.** The owner invites users and gives each a role per environment (viewer, operator or admin), using spatie/laravel-permission. Host routes are already scoped per environment and go through policies, so this doesn't need a rewrite.
- **Streaming logs** for applications, containers and the journal, instead of polling.
- **Live host state for changes made outside Pods**, such as a unit that crashed or finished starting, which still need polling. That would take a watcher of `podman events` per host that broadcasts them, which would keep an on-demand Pods awake.
- **More of the application catalog:** brand logos, and more of lerd.sh's services in [foxws/quadlets](https://github.com/foxws/quadlets).
- **Application metadata on the Applications list**: the label, description and icon of the stored `pods.yml`, as the application page shows them.
- **Show the commands.** Each action can show the `lpod …` commands it runs, with a copy button, so they can be run on the host by hand. Multi-step flows (install, update) show the commands in order, and a secret or a password shows as a placeholder on stdin, never its value.
- **Labels on resources.** Tags, a color and a description for applications, containers, pods, images, volumes, networks and secrets, to filter the lists by. These live on the host, so Pods keeps them in a table of its own keyed by environment, kind and name (not a container's ID, which changes when systemd recreates it), tagged with spatie/laravel-tags, and forgets the rows of resources that are gone, like `pods:sync`. A description of its own shows instead of the one in `pods.yml`.
- **A terminal.** A shell in a container (`podman exec -it`) or on the host, in the browser with xterm.js. lpod's JSON contract doesn't fit a two-way stream, and Octane workers shouldn't hold one open, so this would be a package of its own: a small bridge between a WebSocket and a PTY over SSH, run as its own Quadlet, that Pods hands a short-lived signed token per session. It needs the invited users' roles first (only an admin of an environment gets a shell), an idle timeout, and a log of who opened which shell.
- **Compose to Quadlet.** A converter, possibly AI-assisted. Pods itself stays Quadlet-only: no Docker and no Podman Compose.
