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

**Upstreams.** A route's upstream is a name or address and a port:

| Upstream                        | For                                                                                       |
| ------------------------------- | ----------------------------------------------------------------------------------------- |
| `systemd-hello:8000`            | A container on one of the application's networks, which the proxy joins. The usual choice |
| `host.containers.internal:3000` | A port on the proxy's own host, published on an address other than `127.0.0.1`            |
| `192.168.1.20:8080`             | Another machine on your network                                                           |

The routes modal lists where the application's containers can be reached under **Where APP listens**, from the ports they publish and the networks they're on, and **Pick** fills one in. Pods can't see the ports of a container on the host network, so those show as unknown.

IPv6 addresses aren't supported. A container name only resolves on a network the proxy shares with it, so an application without a `Network=` (on Podman's default pasta network, or `Network=host`) is reached through the host. Use `host.containers.internal` for that, not the host's own IP: rootless Podman gives the proxy's network the host's address, so a connection to it stays inside the container's namespace and is refused. The proxy can't reach the host's `127.0.0.1` either, so a port published as `PublishPort=127.0.0.1:3000:3000` needs another publish first, in a drop-in on the application's Files tab:

```ini
# adguardhome.container.d/proxy.local.conf
[Container]
# Publishes the web UI on the host's LAN address too, so the Pods proxy can reach it as host.containers.internal.
PublishPort=192.168.1.10:3000:3000
```

Restart the application, then route to `host.containers.internal:3000`. Give the host a fixed address, or the drop-in breaks when it changes, or publish on every address instead (`PublishPort=3001:3000`, routed to `host.containers.internal:3001`), which needs another host port since `127.0.0.1:3000` is taken. Either way the port is reachable from your network without HTTPS, so block it in the host's firewall if the application's own login isn't enough. This is how to route AdGuard Home: on a bridge network, rootless Podman would show every DNS client as the gateway's address, so it stays on pasta.

**Forwarded headers.** Pods believes `X-Forwarded-For`, `-Port` and `-Proto` from private addresses only (`TRUSTED_PROXIES` in the `pods-env` secret picks others), and never `X-Forwarded-Host`, so a proxy in front has to pass the `Host` header on (Caddy does, nginx needs `proxy_set_header Host $host;`). Links in password reset mails use `APP_URL`, as passkeys do, so set it to the address you reach Pods at.

To install it by hand instead:

```bash
podman quadlet install --application=pods-proxy containers/proxy
echo '{"mode": "local", "http_port": 80, "https_port": 443}' | lpod proxy settings set --stdin --json
systemctl --user start pods-proxy
```
