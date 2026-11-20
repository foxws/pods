---
title: Proxy
section: Configuration
order: 2
tags:
    - proxy
    - caddy
    - certificates
    - routes
---

# Proxy

`containers/proxy/` is an optional reverse proxy for a host: Caddy, as plain Quadlet files. It routes hostnames to the containers of the applications on that host, so the applications only need `ExposeHostPort=` and never clash over host ports.

Install it from the Proxy section of a host's settings in Pods. Pick how it gets certificates, and its ports:

| Certificates                | For                                                                                                                                                                                | Hostnames look like                         |
| --------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------- |
| Let's Encrypt               | A public domain the internet reaches on port 80 or 443                                                                                                                             | `hello.example.com`, `ws.hello.example.com` |
| Local certificate authority | Names such as `.home.arpa`, `.lan` or `.test`. Download the root certificate from the Proxy section, and trust it once on each device                                              | `hello.home.arpa`, `ws.hello.home.arpa`     |
| Wildcard through DNS        | A public domain on a host only your network reaches: one certificate for `*.DOMAIN` through your DNS provider (Cloudflare), whose API token is kept as a Podman secret on the host | `hello.DOMAIN`, `hello-ws.DOMAIN`           |
| Plain HTTP                  | Another proxy in front that handles HTTPS                                                                                                                                          | as the front proxy routes them              |

Pods installs `containers/proxy` from this repository as the application `pods-proxy`, and lpod writes `~/.config/pods-proxy/Caddyfile` and the drop-in `pods-proxy.container.d/lpod-proxy.conf` with the ports, the image and the DNS token (see `lpod proxy settings` in lpod's `docs/commands.md`). The wildcard mode runs `ghcr.io/foxws/pods-proxy:main`, Caddy with the Cloudflare plugin (`containers/runtimes/proxy/Containerfile`, rebuilt every week); the others run `caddy:2`, and any of them can run your own image. Saving replaces a Caddyfile you wrote yourself, and keeps yours next to it as a backup, so keep sites of your own in `~/.config/pods-proxy/sites/*.Caddyfile`. Rootless Podman can't publish ports below `net.ipv4.ip_unprivileged_port_start` (1024 by default), so Pods warns about 80 and 443 there: allow them, or use ports such as 28080 and 28443 behind the host's main proxy, which is also how each user on a shared host runs their own proxy. `install.sh --proxy` installs it too, with Let's Encrypt (or plain HTTP with `--behind-proxy`) on `--proxy-ports`, the first time only, and routes `--host` to Pods as a route of the application `pods`, which you edit on its page like any other.

**Proxy defaults.** Under Settings, Proxy, the owner sets what a host's Proxy section starts from before its proxy is installed (the mode, email, ports, image), so installing it on another host is one click. The domain there is the one Pods suggests hostnames under (`hello.example.com`, `ws.hello.example.com`) instead of `home.arpa`, except behind a wildcard site, whose own domain wins. Hosts with a proxy keep their own settings, and the DNS token is never part of the defaults: it's asked for per host, and only kept there.

Once the proxy is installed, Pods manages the routes: the install page asks for a hostname for each route an application's `pods.yml` names, and the Routes card on the application page adds, changes and removes them. lpod writes them to `~/.config/pods-proxy/sites/`, with a drop-in that joins the application's networks, then reloads Caddy, or restarts the proxy when it joins a new network. The main route of an on-demand application goes to its socket, so a request wakes it.

To install it by hand instead:

```bash
podman quadlet install --application=pods-proxy containers/proxy
echo '{"mode": "local", "http_port": 80, "https_port": 443}' | lpod proxy settings set --stdin --json
systemctl --user start pods-proxy
```
