---
title: Application Manifest
section: Configuration
order: 1
tags:
    - pods.yml
    - manifest
    - catalog
    - secrets
    - timers
---

# Application manifest (pods.yml)

A repository can tell Pods how to install it with a `pods.yml` in its root, or in a directory per application. When you add an application from Git, Pods reads it to offer the repository's Quadlet directories, fill in the application name, and explain and fill in the secrets the units need. It never changes the Quadlet files: they stay installable with plain `podman quadlet install`, and a repository without `pods.yml` installs as before.

```yaml
version: 1 # Pods ignores manifests of a version it doesn't know
name: hello # the default application name
label: hello # shown in the catalog, like the keys below
description: Self-hosted video library
author: Example
icon: i-lucide-film # a Lucide icon
category: Media
website: https://hello.example.com
repository: https://github.com/example/hello # its source code
notes: Shown before installing.

sources: # Quadlet directories to choose from, relative to this pods.yml
    production:
        path: containers/quadlets/production
        description: The published image

secrets: # how to ask for the secrets the units use
    hello-env:
        description: Your .env file
        format: dotenv # a text area; other secrets can read keys from it
    hello-pgsql-password:
        from: hello-env # filled in from DB_PASSWORD in the .env above
        key: DB_PASSWORD
    hello-app-key:
        generate: true # filled in with a random value

after_install: # commands to run once the units are up, from the application page
    install:
        label: Install hello
        container: hello # the container's Quadlet, without .container
        user: docker # optional, like workdir
        command: php artisan app:install --no-interaction # split on whitespace
    admin:
        label: Create the first admin
        container: hello
        command: php artisan users:create --super-admin --password-stdin --no-interaction
        inputs: # asked for before the step runs
            name:
                option: --name
            email:
                type: email # text (the default), email or password
                option: --email # passed as --email=VALUE
            password:
                type: password # an input without an option goes on stdin

after_update: # commands to run after each update, once the new units are up; names differ from after_install's
    migrate:
        label: Migrate the database
        container: hello
        user: docker
        command: php artisan migrate --force --no-interaction

routes: # upstreams the Pods proxy can give a hostname; Pods asks for the hostnames
    app:
        upstream: systemd-hello:8000 # a container and its port
        description: The web app # the first route is the main one, unless another says main: true
    ws:
        upstream: systemd-hello-reverb:6001
        via: main # optional: go where the main route goes, for an app that routes its own hostnames

ondemand: # fills in the on-demand switch; the keys match lpod's ondemand status
    port: 8000 # the socket on the host
    app_port: 8000 # inside the app container, read from its unit when left out
    idle_timeout: 10min
    workers: [hello-horizon] # keep running until the idle check says there's no work
    idle_check:
        container: hello-horizon
        user: docker
        command: php /app/artisan app:idle
    schedule: # runs schedule:run only at these minutes, in UTC
        container: hello-schedule
        at: ['*:00', '01:00']
        environment:
            APP_COMMAND: php /app/artisan schedule:run

timers: # containers a systemd timer starts on a schedule; the keys are the directives they become
    backup: # lpod writes APP-NAME.timer, here hello-backup.timer
        description: Backs up the database
        container: hello-backup # a container Quadlet of the app that runs once and exits (Type=oneshot)
        on_calendar: daily # OnCalendar=, or a list of calendar events
        persistent: true # Persistent=: catch up on a run missed while the host was off
        randomized_delay_sec: 30min # RandomizedDelaySec=
        timeout_start_sec: infinity # TimeoutStartSec= for the container, in a drop-in
```

Every key is optional. When you fetch a directory, Pods reads the nearest `pods.yml`: the one in that directory, or else in a directory above it. Secret values stay in the browser until you install, and go to the host on stdin like any other secret. After an update, the application page asks for the new release's `after_update` steps, which take inputs like setup steps, and only counts a step as done once it ran since that update. A step's inputs aren't stored; at most one input goes on stdin, and a password always does, so it never shows in the arguments. A manifest Pods can't read is shown as a warning and ignored, so the files can still be installed.

Pods sets up the `timers` after installing and after each update, so they follow the release: a timer the new `pods.yml` no longer names is removed. The application page has a Timers card with each timer's schedule, next and last run, and **Run now**. A timer starts what its container `Requires=`, so it also wakes an on-demand application, which sleeps again after its idle timeout. `timers` are for jobs that live on the host; a Laravel scheduler stays in `ondemand.schedule`, since it only runs from a timer while the application is on demand.

The applications in [foxws/quadlets](https://github.com/foxws/quadlets) each have a `pods.yml` to start from. Backup hints are planned; see the [roadmap](roadmap.md).

## Catalog

The Install application page lists the applications of the repositories in `config/catalog.php`, [foxws/quadlets](https://github.com/foxws/quadlets) by default. Every directory at such a repository's root with a `pods.yml` is one application, such as `mariadb/pods.yml` with `production` and `development` sources next to it. The host clones the repository to read them, and Pods keeps the list for a few minutes.
