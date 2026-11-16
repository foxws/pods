# Roadmap

Pods is still in development. This is what's planned, roughly in order. Nothing here is a promise, and the order can change.

## Volume backups

Pods doesn't back up volumes yet. Until it does, back them up on the host yourself (see [Backups in the README](../README.md#backups)).

The plan is to export volumes from the UI, without keeping the browser waiting:

1. **lpod.** A `lpod volume export NAME` command that streams `podman volume export` as a tar archive to stdout, and an option to stop the application's units first and start them again afterwards for a consistent copy.
2. **Queue.** The Volumes page dispatches a queued job (for example `ExportVolume`) for one volume or every volume of an application. The job streams the archive over SSH straight to a Pods disk, so a large volume never sits in memory. It needs a much longer timeout than the 30 seconds that normal lpod calls get.
3. **Notify.** When the archive is ready, the job sends the owner a notification (in the app and by mail) with a temporary signed download link. A failed export sends a notification with lpod's error.
4. **Retention.** Old archives are pruned on a schedule, with a configurable number of archives kept per volume.

Open questions:

- Bind mounts (such as `/var/mnt/photos`) aren't Podman volumes, so `podman volume export` doesn't cover them. They may need a separate `tar` path, or stay out of scope.
- Databases are safer to back up with their own dump tool. A per-application hook, such as a `Label=` with a dump command, could handle that.
- Where the archives go: the Pods data volume, or an S3-compatible disk.
- Scheduled backups, and restoring with `podman volume import`.

## Later

- **Invited users.** The owner invites users and gives each a role per environment (viewer, operator or admin), using spatie/laravel-permission. Host routes are already scoped per environment and go through policies, so this doesn't need a rewrite.
- **Streaming logs** for applications, containers and the journal, instead of polling.
- **An application catalog** of presets that can be installed in a few clicks.
- **Quadlet metadata.** [laravel-podman](https://github.com/foxws/laravel-podman) emits `Label=` lines (description, URL, icon) that the UI shows next to each application.
- **Packaging.** Pods ships as its own socket-activated Quadlets (like `cockpit.socket`), with SQLite on a volume and the database queue, so it can sleep when nobody uses it. An install script sets up the units, the SSH key and the owner.
- **Compose to Quadlet.** A converter, possibly AI-assisted. Pods itself stays Quadlet-only: no Docker and no Podman Compose.
