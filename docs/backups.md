---
title: Backups
section: Configuration
order: 4
tags:
    - backups
    - volumes
    - exports
---

# Backups

**Volume sizes.** The Volumes page shows how much disk space each named and anonymous volume uses once lpod has measured it, a moment after the list, since it walks every file; sizes are kept for 10 minutes. Volumes backed by a folder, a device or an NFS share aren't measured.

**Volume exports.** On the Volumes page, a volume's actions export it, or every volume of an application that uses it. Pods streams the volume from the host into a tar archive on its own data volume (`podman volume export`, through `lpod volume export`), in the background, so the export can take as long as it needs (up to an hour, `PODS_VOLUME_EXPORT_TIMEOUT`). Tick **Stop … while exporting** for a consistent copy of files that change while the application runs; it starts again afterwards. When the archive is ready, Pods shows a notification with a download link on whichever page you have open and keeps it on the Notifications page (the bell in the header), so you see it even with no page open, and the Exports card on the Volumes page lists every export of the host with its size or its error. Each night, Pods keeps the newest three archives of each volume (`PODS_VOLUME_EXPORTS_KEPT`), and removes failed exports after a week.

Exports run in `pods-queue`, Pods' queue worker. It keeps running while an on-demand Pods sleeps, until the queue is empty. Only volumes in Podman's own storage can be exported: a volume backed by a folder on the host (`Type=none`, `Options=bind`, `Device=`, such as a media library), an NFS volume and a bind mount (such as `/var/mnt/photos`) are left out. Copy those with your usual tools. Databases are safer to back up with their own dump tool.

Pods doesn't restore archives yet (see the [roadmap](roadmap.md#volume-backups-and-restores)). To restore one on the host:

```bash
systemctl --user stop immich.service        # stop the application first
podman volume import immich-library immich-library.tar
systemctl --user start immich.service
```
