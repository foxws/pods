---
title: Compared with lerd
section: Getting Started
order: 4
tags:
    - lerd
    - comparison
    - postgres
    - development
---

# Pods compared with lerd

[lerd](https://lerd.sh) and Pods both run applications as rootless Podman containers under systemd, without a daemon of their own, and both can let services sleep while nobody uses them. They aim at different machines, though. lerd is a local development environment for PHP projects on your own computer, while Pods runs applications on your servers and homelab, over SSH. This page compares the two, and shows how to set up in Pods what lerd sets up for you, such as one PostgreSQL shared by several applications.

## At a glance

|                        | lerd                                                                                             | Pods                                                                                                                      |
| ---------------------- | ------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------- |
| Made for               | Developing PHP projects (Laravel, Symfony, WordPress and more) on your own machine               | Running any containerized application on one or more hosts                                                                |
| Where it runs          | Linux, macOS, and Windows through WSL2                                                           | Podman 6+ hosts with systemd, reached over SSH, the one Pods runs on too                                                  |
| An application is      | A project folder; lerd detects its framework                                                     | A group of plain Quadlet files (`.container`, `.volume`, `.network`, …) from a Git repository or the catalog              |
| Per-project config     | Optional `.lerd.yaml` (PHP and Node versions, services, workers)                                 | Optional [`pods.yml`](manifest.md) (secrets, setup steps, routes, on-demand, timers); the Quadlet files work without it   |
| PHP                    | Shared PHP-FPM per version, 7.4 to 8.6, picked per site (`lerd isolate 8.4`), Xdebug as a toggle | Whatever the application's image has; each application brings its own runtime                                             |
| Hostnames and TLS      | `https://project.test` with a locally trusted certificate, through nginx                         | The optional [Pods proxy](proxy.md) (Caddy): Let's Encrypt, a local CA, a wildcard certificate through DNS, or plain HTTP |
| Databases and services | Presets (MySQL, PostgreSQL, Redis, Meilisearch, RustFS, Mailpit, …) that write `.env` for you    | Catalog applications (PostgreSQL, MariaDB, Valkey, Typesense, RustFS, Mailpit, …), or units the application ships itself  |
| Sleeping when idle     | Services suspend when unused                                                                     | [On demand](ondemand.md): a systemd socket wakes the application on its first request, and an idle check stops it again   |
| Updates                | Framework definitions update without a release                                                   | Releases of the application's repository (Git tags or a branch), with `after_update` steps, plus `podman auto-update`     |
| Developer extras       | `lerd init`, worktrees with their own domain and database, SPX profiling, a TUI, an MCP server   | None of these: Pods manages what runs, not your editor or checkout                                                        |
| Removing it            | `lerd uninstall` removes its units, network and binary                                           | Remove Pods and every application keeps running: the Quadlet files stay systemd units                                     |

## When to use which

- **Developing a PHP project on your laptop:** lerd. It detects the framework, runs Composer and npm, gives the project a `.test` domain with a trusted certificate, and switches PHP versions per project. Pods has none of that.
- **Running applications on a server or homelab:** Pods. It installs them from Git, keeps their secrets on the host, gives them hostnames and certificates, runs their setup steps, and updates them to new releases, on as many hosts as you add.
- **Both:** develop with lerd, and ship Quadlet files and a `pods.yml` in the repository so Pods can install the release. Pods' own repository does this: the root [`pods.yml`](https://github.com/foxws/pods/blob/main/pods.yml) installs the published image, and the one in `containers/quadlets/development` runs a checkout as `pods-dev`, with routes for the app, Vite, Reverb and Mailpit.

## One PostgreSQL for several applications

lerd starts one PostgreSQL that every project uses, each with its own database. Pods doesn't do this for you, but it takes three steps, using plain Quadlet features:

1. **Install PostgreSQL once.** Install `postgres` from the catalog (Install application, PostgreSQL, the production source). It generates the password of the `postgres` superuser as the secret `postgres-password`, and other applications reach it as `postgres` on the `systemd-postgres` network.
2. **Let each application join that network.** On the application page, open **Quadlet files** and add a drop-in to every container that uses the database (the app, and its queue worker or scheduler), such as `hello.container.d/postgres.local.conf`:

    ```ini
    [Unit]
    # Start PostgreSQL first, and stop with it.
    Requires=postgres.service
    After=postgres.service

    [Container]
    # Another application's network. Quadlet finds the file, so this adds the
    # dependency on systemd-postgres too. Network= adds up in drop-ins, so the
    # application keeps its own network.
    Network=postgres.network
    ```

    Pods never tracks or removes a `*.local.conf` drop-in, so it stays through updates. Remove the drop-ins before removing `postgres`, or Quadlet won't generate the applications that reference its network.

3. **Give each application its own database.** Point each application's `.env` at the shared server with a database name of its own:

    ```dotenv
    DB_CONNECTION=pgsql
    DB_HOST=postgres
    DB_PORT=5432
    DB_DATABASE=hello
    DB_USERNAME=postgres
    DB_PASSWORD=   # the value of the postgres-password secret
    ```

    A Laravel application needs nothing else: `php artisan migrate --force` creates a missing PostgreSQL database before it migrates, so the application's `migrate` setup step (in its `pods.yml`, under `after_install`) creates `hello` on the first run.

Every application now shares one PostgreSQL, each in its own database, as with lerd. Two differences to keep in mind:

- **One superuser for every application.** This matches what a local development environment does, but on a server you may want a role per application, so one can't read another's data. Create it once in a terminal on the host with `podman exec -it systemd-postgres psql -U postgres`, then `CREATE ROLE hello LOGIN PASSWORD '…';` and `CREATE DATABASE hello OWNER hello;`, and use that role in the `.env`.
- **PostgreSQL keeps running.** The catalog's `postgres` starts at boot and doesn't sleep. The applications that share it can still be [on demand](ondemand.md): they sleep, and only the shared server stays up for them.

The same steps work for any service the catalog has, such as Valkey, Typesense or Mailpit: install it once, join its network with a local drop-in, and point each application's `.env` at it. Give each application its own key prefix, index or mailbox, as lerd does.
