---
title: Screenshots
section: Getting Started
order: 1
tags:
    - gallery
    - screenshots
---

# Screenshots

A quick look at what Pods looks like.

:::note
Pods is still in development, so the screenshots may be out of date. The current version can look different.
:::

## Overview

Each host starts on its overview: CPU, memory, disk and containers, what needs attention (such as failed units), the host's recent activity from its journal, its applications, and the Podman and systemd settings that matter, such as auto-update and lingering.

![Host overview with CPU, memory, disk and container cards, recent activity, applications and system details](https://raw.githubusercontent.com/francoism90/.github/main/assets/pods/overview.webp)

## Applications

Every Quadlet application on the host, however it was installed, with its status, units, image, ports and memory. Filter by running, stopped or failed, check for updates, and start, stop, restart or remove an application from its menu.

![Applications page with a table of Quadlet applications, their status, units, images, ports and memory](https://raw.githubusercontent.com/francoism90/.github/main/assets/pods/applications.webp)

## Containers

Every container on the host, including ones started outside Quadlet, with the application it belongs to, its image, ports, CPU, memory and uptime.

![Containers page with a table of containers, their applications, images, ports, CPU, memory and uptime](https://raw.githubusercontent.com/francoism90/.github/main/assets/pods/containers.webp)

## Images

The images in the user's rootless store, which application uses each one, and whether `podman auto-update` pulls it. Prune unused images, pull a new one, or check every image for updates.

![Images page with a table of images, their tags, sizes, the applications using them and their auto-update policy](https://raw.githubusercontent.com/francoism90/.github/main/assets/pods/images.webp)

## Volumes

Named, bind and NFS volumes, the Quadlet that creates each one, its mount point, how much disk space it uses and which containers mount it. Volumes can be exported as tar archives (see [Backups](backups.md)).

![Volumes page with a table of volumes, their drivers, mount points, sizes and containers](https://raw.githubusercontent.com/francoism90/.github/main/assets/pods/volumes.webp)

## Networks

The Netavark networks on the host, with their subnet, gateway, DNS, and the containers and applications attached to them.

![Networks page with a table of networks, their drivers, subnets, gateways, DNS and applications](https://raw.githubusercontent.com/francoism90/.github/main/assets/pods/networks.webp)
