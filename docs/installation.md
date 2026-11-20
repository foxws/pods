---
title: Installation
section: Getting Started
order: 2
tags:
    - install
    - requirements
    - podman
    - ssh
---

# Installation

## Requirements

On each host:

- Podman 6 or newer, running under systemd. Quadlet is part of Podman, so there's nothing else to install for it. Most major distributions package Podman 6 in their current releases; check with `podman --version`
- [lpod](https://github.com/foxws/lpod) 4 in the user's `PATH` (Pods also looks in `~/.local/bin`), with `jq` installed, and `git` for installing and updating applications from Git repositories and the catalog
- An SSH server, and lingering for rootless users (`loginctl enable-linger`) so their systemd user manager runs without a login

For Pods itself: PHP 8.5+, Node with pnpm, and `ssh` and `ssh-keygen` on the machine or in the container that runs it. Its units use `RunInit=true`, which needs Podman's `catatonit` (`install.sh` checks for it).

## Install

Pods runs on a host as rootless Podman Quadlets, for the user whose applications it manages. One command installs it, and running it again updates it:

```bash
curl -fsSL https://raw.githubusercontent.com/foxws/pods/v1.0.0-rc.8/install.sh | bash
```

It checks Podman, systemd, lingering and SSH, installs lpod, installs Pods' units from `containers/quadlets/production` and its image from `ghcr.io/foxws/pods`, adds Pods' SSH key to your `~/.ssh/authorized_keys` so the host shows up as the local environment, and prints a one-time setup code. Open Pods, which sends you to its setup page, and enter the code to create the owner (or pass `--name`, `--email` and `PODS_OWNER_PASSWORD` to create the owner from the terminal instead). Pods listens on `127.0.0.1:9393` (`--port` picks another; Cockpit has 9090): open it on the host, or through an SSH tunnel (`ssh -L 9393:127.0.0.1:9393 HOST`). Running it again keeps the port, and updates lpod when it's older than the release needs.

### Pods only runs while you use it

Pods is a container like any of your applications, not a service your host depends on. Your applications are systemd units, so they keep running, restarting and updating whether Pods is up or not.

By default, `install.sh` doesn't keep Pods running. Like `cockpit.socket`, a systemd socket (`pods-ondemand.socket`) listens on `127.0.0.1:9393` and starts Pods on the first request. Pods stops again after 10 minutes without one, and its scheduler only wakes for a moment at 01:00 UTC for the daily housekeeping. While Pods has a route through the [proxy](proxy.md), the socket listens on every address instead, and the route goes through it, so a request through the proxy wakes Pods too. You can also start and stop it yourself:

```bash
lpod pods down                                # stop Pods now; the next request starts it again
systemctl --user stop pods-ondemand.socket    # keep it off, even when something connects
systemctl --user start pods-ondemand.socket   # let the next request start it again
```

Pass `--no-idle` to keep Pods running all the time, and start and stop it as `pods.service` instead.

**Behind the proxy**, with `--proxy` or a route set in Pods (even when `install.sh` runs again without `--proxy`), the proxy container reaches the host as `host.containers.internal`, which isn't `127.0.0.1`. So `pods-ondemand.socket` listens on port 9393 on every address, and lpod points Pods' route at it. That port is then reachable from your network too, so check that the host's firewall blocks it (Fedora Server's default firewalld zone only opens the services it lists, but Fedora Workstation's opens every port from 1025 up), or pass `--no-idle` to keep Pods on `127.0.0.1`. A unix socket for the proxy doesn't work instead: SELinux doesn't let a container connect to a socket the user's systemd holds, and a user's socket units can't limit who connects.

### A hostname for Pods

To reach it by a hostname, add the [proxy](proxy.md):

```bash
curl -fsSL https://raw.githubusercontent.com/foxws/pods/v1.0.0-rc.8/install.sh | bash -s -- --proxy --host pods.example.com
```

On a host shared with other users, give the proxy other ports with `--proxy-ports 28080,28443`, and add `--behind-proxy` when another proxy in front handles HTTPS. `--uninstall` removes Pods and keeps its data; add `--purge` to remove that too. `--help` lists every option.

To prepare a host (or another user on the same machine) for a Pods that runs elsewhere, use `--no-pods`. It checks the requirements and installs lpod, and `--authorized-key` adds Pods' public key (**Hosts**, **Copy public key**) to that user's `~/.ssh/authorized_keys`. It installs no Pods and no proxy: install the proxy from Pods, in the new environment's settings.

```bash
curl -fsSL https://raw.githubusercontent.com/foxws/pods/v1.0.0-rc.8/install.sh | bash -s -- --no-pods --authorized-key 'ssh-ed25519 AAAA… pods'
```
