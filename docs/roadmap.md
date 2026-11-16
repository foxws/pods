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

## More of pods.yml

Pods reads the sources, the application name and the secrets of a repository's `pods.yml` today. Next:

1. **Setup steps.** `after_install` lists the commands to run once the units are up, such as `php artisan app:install` and creating the first admin, with inputs the UI asks for (a password goes on stdin, never in the arguments). lpod needs `lpod container NAME exec --stdin --json -- COMMAND`, and Pods needs to remember the manifest of an installed application (a table keyed by environment and application, with the repository, tag and commit it came from), so the application page can show a "Finish setup" panel. This replaces what laravel-installer did.
2. **On-demand.** `ondemand` (port, idle timeout, idle check and the workers to stop) lets lpod write the socket, proxy and idle units, so an application doesn't ship them itself. See [On-demand applications](#on-demand-applications).
3. **Backups.** `backups` lists the volumes to leave out (such as bind-backed ones) and a database dump command. See [Volume backups and restores](#volume-backups-and-restores).
4. **Updates.** With the stored source, the application page can offer "Update to tag X" and reinstall from it with `--replace`.

## Installing applications from a checkout

Pods installs Quadlets from Git. Development setups, such as stry's `containers/quadlets/development`, build their image from a checkout on the host and mount it into the containers. `lpod quadlet fetch` should read a directory on the host as well as a Git repository, so Pods can install those, and show the build's progress while the first start builds the image.

## Later

- **Invited users.** The owner invites users and gives each a role per environment (viewer, operator or admin), using spatie/laravel-permission. Host routes are already scoped per environment and go through policies, so this doesn't need a rewrite.
- **Streaming logs** for applications, containers and the journal, instead of polling.
- **An application catalog** of repositories with a `pods.yml`, installable in a few clicks.
- **Application metadata.** The name and description from `pods.yml` (and a URL and icon) shown next to each installed application.
- **Packaging.** Pods ships as its own plain, socket-activated Quadlets (like `cockpit.socket`), with SQLite on a volume and the database queue, so it can sleep when nobody uses it. An install script sets up the units, the SSH key and the owner.
- **Compose to Quadlet.** A converter, possibly AI-assisted. Pods itself stays Quadlet-only: no Docker and no Podman Compose.
