---
title: Getting Started
section: Getting Started
order: 3
tags:
    - setup
    - catalog
    - quadlet
    - first-application
---

# Getting started

Once `install.sh` is done:

1. **Create the owner.** Open `http://127.0.0.1:9393` on the host (or `ssh -L 9393:127.0.0.1:9393 HOST` and open it on your own machine). Pods sends you to **Set up Pods**. Enter the setup code `install.sh` printed, then your name, email address and password. If you lost the code, `journalctl --user -u pods` shows it again.
2. **Check the local host.** **Hosts** lists the machine Pods runs on as the local environment, already connected. Its overview shows CPU, memory, disk and any failed units.
3. **Install an application from the catalog.** Open **Applications**, then **Install application**, and search the **Catalog** for Memos. Pick it, check the Quadlet file Pods shows you, keep **Start after installing** on, and press **Install memos**. Memos listens on `127.0.0.1:5230` on the host. Its page has its containers, its journal, and Start, Stop, Restart, and Pull and redeploy.
4. **Add another host (optional).** As the user that owns the Quadlets there, run `install.sh --no-pods --authorized-key '…'` with Pods' public key (**Hosts**, **Copy public key**), or install lpod (see [Requirements](installation.md#requirements)) and add the key to `~/.ssh/authorized_keys` yourself. Then use **Add environment** with its hostname and that user. Another user on the machine Pods runs on is `host.containers.internal`, with its own lingering (`sudo loginctl enable-linger USER`), and its proxy on ports such as 28080 behind the main one. Pods pins the host's key on first contact.

Each Pods release needs a matching lpod. When a host has another one, its **Settings** page offers **Update to vX**, which runs `lpod self-update` over SSH as that host's user, since each user has their own lpod. Pods then checks the version the host reports.

Next, give an application a hostname with the [proxy](proxy.md), or make your own repository installable, as below.

## Your first application

An application is a group of plain Quadlet files. Pods shows every application Podman knows about (`podman quadlet list`), however it got there:

- **From a Git repository** with Quadlet files, through **Install application**, **Git repository**. This is the only way that stores where the files came from, so Pods can offer updates and setup steps. A `pods.yml` is optional.
- **From Quadlet files you paste** on the same page.
- **From a terminal**, with `podman quadlet install` or `lpod quadlet install`. Pods shows these too, with their containers, logs and Start and Stop, but without a source to update from.

A `pods.yml` next to the files tells Pods what to call the application and which routes it has. This example runs [traefik/whoami](https://github.com/traefik/whoami), a tiny web server that answers with the request it got:

```text
hello/
├── pods.yml
└── quadlets/
    ├── hello.container
    └── hello.network
```

`quadlets/hello.network` gives the application a network of its own, which the proxy joins to reach it by name:

```ini
[Network]
```

`quadlets/hello.container`:

```ini
[Unit]
Description=Hello

[Container]
Image=docker.io/traefik/whoami:latest
AutoUpdate=registry
Network=hello.network
# For the proxy. Without it, publish a port instead: PublishPort=127.0.0.1:8080:80
ExposeHostPort=80

[Service]
Restart=on-failure

[Install]
WantedBy=default.target
```

`pods.yml`:

```yaml
version: 1
name: hello
label: Hello
description: Answers with the request it got
icon: i-lucide-hand

sources:
    production:
        path: quadlets

routes:
    app:
        upstream: systemd-hello:80 # Quadlet names the container systemd-hello
        description: The web page
```

Push it, then in Pods:

1. Open **Install application**, **Git repository**, enter the repository URL and **Fetch repository**. Pods reads `pods.yml`, fills in the name, and shows the two files.
2. Under **Hostnames**, press **Suggest** for a name such as `hello.home.arpa`, or type your own. This needs the [proxy](proxy.md) on that host. Without it, leave the hostname empty and use the `PublishPort=` line instead.
3. Press **Install hello**. Open the hostname, and whoami answers. You can change the hostname later on the application's **Routes** card.

The files stay plain Quadlets, so `podman quadlet install --application=hello quadlets` installs the same thing without Pods. Installed from a branch, the application's page offers its newest commit as an update. Installed from a tag, it offers newer tags. For secrets, setup steps and on-demand, see [pods.yml](manifest.md). [foxws/quadlets](https://github.com/foxws/quadlets) has more examples.
