# Roadmap

Pods is still in development. This is what's planned, roughly in order. Nothing here is a promise, and the order can change.

## Volume backups and restores

Pods exports volumes (see [Backups in the README](../README.md#backups)), but doesn't restore them yet. Next:

1. **lpod.** `lpod volume import NAME --stdin [--stop] --json` reads an archive into `podman volume import`, creates the volume first when it doesn't exist, and refuses `device` and NFS volumes, like `volume export`.
2. **Restore.** An import starts from an archive Pods made, or one the owner uploads. It replaces the volume's contents, so the UI asks for confirmation, shows which applications it stops, and offers to export the current contents first. It runs in `pods-queue` like an export, streaming the archive over SSH from the backups disk, and sends the same kind of notification.

Open questions:

- **Plain bind mounts** (such as `Volume=/var/mnt/photos:/photos`) aren't Podman volumes, so `podman volume export` doesn't cover them. They may need a separate `tar` path, or stay out of scope.
- **Databases** are safer to back up with their own dump tool. A per-application hook, such as a `Label=` with a dump and a restore command, could handle that.
- **One stop for an application.** Exporting every volume of an application with its applications stopped stops and starts them once per volume.
- **Another disk**, such as an S3-compatible one, for the archives.
- **Scheduled backups**, with the same retention.

## Proxy

Still open for the optional per-host proxy (see [Proxy in the README](../README.md#proxy)):

- **A real-host check:** installing the proxy in each mode from Pods, the root certificate download, and `install.sh --proxy` on a host set up by an older install.sh. So far it's only tested against `fakeLpod()` and stubbed binaries.
- **More DNS providers** for the wildcard mode, each a `--with` line in `containers/runtimes/proxy/Containerfile`, a name in `ProxySettingsData::DnsProviders` and its token variable in lpod.
- **Routes to sleeping services that aren't `via: main`.** These still get a 502, such as Mailpit or the RustFS console opened on their own. Caddy could start the app's socket service on a 502 and retry.

## More of pods.yml

1. **Backups.** `backups` lists the volumes to leave out of "export every volume" and a database dump command. See [Volume backups and restores](#volume-backups-and-restores).
2. **Linking to Git.** Pick the source from the repository's `pods.yml` in the "Link to Git" dialog, as the install page does, instead of typing its path.
3. **Timers.** Edit a timer's schedule on the application page, kept over updates like a drop-in.

## Installing applications from a checkout

Pods installs Quadlets from Git. Development setups, such as a Laravel application's `containers/quadlets/development`, build their image from a checkout on the host and mount it into the containers. `lpod quadlet fetch` should read a directory on the host as well as a Git repository, so Pods can install those, and show the build's progress while the first start builds the image.

## Later

- **Invited users.** The owner invites users and gives each a role per environment (viewer, operator or admin), using spatie/laravel-permission. Host routes are already scoped per environment and go through policies, so this doesn't need a rewrite.
- **Streaming logs** for applications, containers and the journal, instead of polling.
- **Live host state for changes made outside Pods**, such as a unit that crashed or finished starting, which still need polling. That would take a watcher of `podman events` per host that broadcasts them, which would keep an on-demand Pods awake.
- **A notifications page.** Notifications are only broadcast now, so a finished export's toast is missed when no page is open. Adding the `database` channel next to `broadcast` keeps them in the existing `notifications` table, so a bell in the header can show the unread count and a page can list them with links to what they're about (an export's download, a restored volume, a failed timer), mark them read and clear old ones. It's worth doing once more than exports notify, such as restores, scheduled backups and the `podman events` watcher above. Once users are invited, each one sees only their own.
- **More of the application catalog:** brand logos, and more of lerd.sh's services in [foxws/quadlets](https://github.com/foxws/quadlets).
- **Application metadata on the Applications list**: the label, description and icon of the stored `pods.yml`, as the application page shows them.
- **Pagination for long lists.** The host lists and the catalog load every row in one lpod call and render them all, which is fine for the tens of applications and containers, or the hundred or so images, a host usually has. Once a list gets long, `DataTable` can show a page at a time with `UPagination` (say 50 rows), after the filter tabs, search and sort, so every list page gets it at once; the applications and secrets pages, which build their own `UTable`, and the catalog grid would need the same. Paging in the browser saves rendering, not host calls: lpod returns the whole list either way, and paging on the host would need offsets in lpod's list commands.
- **Show the commands.** Each action can show the `lpod …` commands it runs, with a copy button, so they can be run on the host by hand. Multi-step flows (install, update) show the commands in order, and a secret or a password shows as a placeholder on stdin, never its value.
- **Labels on resources.** Tags, a color and a description for applications, containers, pods, images, volumes, networks and secrets, to filter the lists by. These live on the host, so Pods keeps them in a table of its own keyed by environment, kind and name (not a container's ID, which changes when systemd recreates it), tagged with spatie/laravel-tags, and forgets the rows of resources that are gone, like `pods:sync`. A description of its own shows instead of the one in `pods.yml`.
- **A terminal.** A shell in a container (`podman exec -it`) or on the host, in the browser with xterm.js. lpod's JSON contract doesn't fit a two-way stream, and Octane workers shouldn't hold one open, so this would be a package of its own: a small bridge between a WebSocket and a PTY over SSH, run as its own Quadlet, that Pods hands a short-lived signed token per session. It needs the invited users' roles first (only an admin of an environment gets a shell), an idle timeout, and a log of who opened which shell.
- **Compose to Quadlet.** A converter, possibly AI-assisted. Pods itself stays Quadlet-only: no Docker and no Podman Compose.
