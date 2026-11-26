---
title: Development
section: Development
order: 1
tags:
    - development
    - podman
    - testing
    - commands
---

# Development

```bash
composer setup     # install, .env, key, migrate and seed the roles, pnpm install and build
php artisan users:create --super-admin
composer dev
```

`users:create --super-admin` asks for the owner's name, email address and password. Or skip it: while Pods has no owner, it sends you to `/setup`, which asks for the code `php artisan pods:setup` prints. Pods has a single owner for now.

Connect the local host:

1. Set `PODS_LOCAL_SSH_USER` in `.env` to the host user that owns the Quadlets. `PODS_LOCAL_SSH_HOST` defaults to `host.containers.internal`.
2. Run `php artisan pods:install`. It generates the SSH key (in `storage/app/private/ssh`), prints the `authorized_keys` line, registers the local environment and checks it.
3. Add that line to `~/.ssh/authorized_keys` on the host, then run `php artisan pods:verify` if the first check failed.

Add remote hosts from **Environments** in the UI, with the same public key.

## Running Pods under Podman

The units in `containers/quadlets/development` run Pods itself from a checkout in `~/projects/pods` (Octane, the queue worker, the scheduler, Reverb, Vite, Valkey and Mailpit). They're named `pods-dev`, so they can run next to an installed Pods. Change the path in `pods-dev.build` and `pods-dev-source.volume` if your checkout is elsewhere.

```bash
podman quadlet install --application=pods-dev containers/quadlets/development
systemctl --user start pods-dev
```

The first start builds the image from `containers/runtimes/development/Containerfile`, on the official FrankenPHP image with PHP 8.6, which takes a while. Add `BuildArg=PHP_VERSION=8.5` to `pods-dev.build` for another PHP version. The units publish no ports. Reach them through the Pods proxy: copy `containers/proxy/sites/pods-dev.Caddyfile` to `~/.config/pods-proxy/sites/`, and `containers/proxy/pods-proxy.container.d/pods-dev.conf` to `~/.config/containers/systemd/pods-proxy.container.d/` with your hostnames. Then set `APP_URL`, `VITE_HMR_HOST` and `REVERB_HOST` (with `REVERB_PORT=443` and `REVERB_SCHEME=https`) in `.env` to match, and restart the proxy. Or install `containers/quadlets/development` from Git in Pods, as `pods-dev`: its `pods.yml` offers these hostnames as routes, and setup steps that migrate, connect the host and create the owner. Without the proxy, publish the app with a drop-in instead (see the comment in `pods-dev.container`). Vite's hot reload then won't reach the browser.

## VS Code Dev Containers

`containers/devcontainer/devcontainer.json` is a config for the [Dev Containers extension](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers), so you can develop Pods inside a container. This is separate from the units above, which run Pods as a service. It joins the `systemd-pods-dev` network, reaches the host as `host.containers.internal` (for `PODS_LOCAL_SSH_HOST`), and gives you PHP IntelliSense, debugging, a terminal and the Claude Code and Codex CLIs. Its image is the `devcontainer` stage of `containers/runtimes/development/Containerfile`: the development image plus Git, the GitHub CLI, Claude Code, Codex and Laravel LSP. The first start builds it, which takes a while. Symlink the config, so it stays up to date when you pull:

```bash
mkdir -p .devcontainer
ln -sf ../containers/devcontainer/devcontainer.json .devcontainer/devcontainer.json
code ~/projects/pods
```

To change it, for example to mount your Git or Claude Code login, copy it to `.devcontainer/devcontainer.json` instead and edit the copy. `.devcontainer/` isn't tracked by Git. `~/.claude.json` must exist as a file on your machine before the first launch; otherwise Podman creates an empty directory with that name. After changing it, run **Dev Containers: Rebuild Container**.

## Structure

```text
src/
├── Domain/        # Environments, Applications, Containers, Pods, Images, Volumes, Networks, Quadlets, Secrets, Users
├── Foundation/    # Providers, middleware, settings and helpers
├── Integrations/
│   ├── Lpod/      # LpodClient: typed methods over the lpod JSON contract
│   └── Ssh/       # SshHostRunner, SshIdentity, host key scanning, FakeHostRunner for tests
├── Modules/
│   ├── Api/       # Versioned API controllers and resources
│   └── Web/       # Inertia controllers, one module per page
└── Support/       # Framework glue: CSP presets, Inertia middleware
```

Pages live in `routes/web.php` and actions in `routes/actions.php`. Everything about a host sits under `/environments/{environment}/…`, so different tabs can stay on different hosts.

## Built with

Laravel 13 on Octane with FrankenPHP, Inertia v3, Vue 3, Nuxt UI 4 and Tailwind CSS 4 (built with Vite+), Fortify (two-factor authentication and passkeys), Reverb with Echo, spatie/laravel-data with TypeScript types, Wayfinder, and [foxws/laravel-ddd](https://github.com/foxws/laravel-ddd). Pods started from [foxws/vue-starter-kit](https://github.com/foxws/vue-starter-kit).

## Commands

```bash
composer dev               # Octane, queue, Reverb, logs and Vite
php artisan pods:install   # Generate the SSH key and register the local host
php artisan pods:verify    # Check that every host answers with a supported lpod contract, and store its versions (also daily at 01:00 UTC)
php artisan users:create   # Create a user (--password-stdin keeps the password out of argv)
composer test              # Pint check and Pest
composer types:check       # Larastan
composer types:generate    # Write TypeScript types for #[TypeScript] classes and enums
pnpm run check:fix         # Format and lint JS, TS and Vue
pnpm run types:check       # vue-tsc
```

## Testing

Tests never connect to a host. `fakeLpod()` in `tests/Pest.php` swaps the SSH runner for a `FakeHostRunner` that answers with the contract fixtures and records which lpod commands ran.

`php artisan test` runs the unit, feature and browser suites. Browser tests use [Pest's browser plugin](https://pestphp.com/docs/browser-testing) and need Chromium:

```bash
npx playwright install --with-deps chromium
php artisan test --testsuite=Browser
```
