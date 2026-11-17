# Architecture

Pods is a Laravel application that manages Podman hosts. It doesn't talk to Podman directly. It runs [lpod](https://github.com/foxws/lpod) on each host over SSH and reads the JSON lpod prints.

```text
Vue page ─▶ Inertia controller ─▶ LpodClient ─▶ SshHostRunner ─▶ ssh ─▶ lpod … --json ─▶ podman / systemctl / journalctl
                                     │
                                     └─ Cache (a few seconds per host and key)
```

## Environments

An environment is one host and one user on it (`src/Domain/Environments/Models/Environment.php`). It stores:

- `name`, `host`, `port` and `user`: where to connect
- `host_key`: the host's public key, pinned when the environment is created
- `is_local`: whether it is the machine Pods runs on
- `facts`: the last `lpod version --json` answer (lpod, Podman and systemd versions, scope, user, hostname)
- `last_seen_at`: when the host last answered

The local host is an SSH target like any other. Pods runs in a container and reaches its own host through `host.containers.internal` (`PODS_LOCAL_SSH_HOST`). `pods:install` registers it from `PODS_LOCAL_SSH_USER`.

Rootless Quadlets belong to a user's systemd manager, so that user needs lingering (`loginctl enable-linger`) to keep the units running without a login session.

## SSH

`src/Integrations/Ssh` holds everything that touches SSH.

- **Identity.** `SshIdentity` manages one ed25519 key pair per Pods instance in `PODS_SSH_DIRECTORY` (default `storage/app/private/ssh`). `pods:install` generates it and prints the `authorized_keys` line.
- **Host keys.** `CreateEnvironment` runs `ssh-keyscan` through `HostKeyScanner` and stores the key. Every connection then uses `StrictHostKeyChecking=yes` with a per-environment `known_hosts` file, so a changed host key fails the connection instead of being accepted.
- **Commands.** `SshHostRunner` builds the `ssh` argument list itself and runs it with Laravel's `Process`, with `BatchMode=yes` and a 10 second connect timeout. Each lpod argument goes through `escapeshellarg`.
- **Login shells.** sshd hands the remote command to the user's login shell, which may be fish or zsh. Pods only sends a small `sh -c` wrapper that every common shell parses the same way. The real command travels as base64, and `sh` decodes and runs it.
- **Secrets.** Values go to lpod on stdin (`$input`), never in the arguments, so they don't show up in `ps`, the shell history or the logs.

`RunsHostCommands` is the contract, bound as a scoped service in `AppServiceProvider` because Octane keeps the application alive between requests.

## The lpod contract

`src/Integrations/Lpod/LpodClient.php` has a typed method per lpod command (`applications`, `containers`, `volumes`, `removeVolume`, `updateHostSettings`, …). Each method:

1. Runs `lpod <command> --json` through the runner.
2. Checks the exit code and the `contract` field. An unknown contract throws `UnsupportedContractException`. Everything else throws an `LpodException` that says what went wrong: the host is unreachable (exit 255), `ssh` or lpod is missing (127), the installed lpod doesn't know the command, the command failed (with lpod's message), or the output isn't JSON.
3. Decodes the payload into spatie/laravel-data objects (`…Data::fromLpod()`) in the matching domain, which are also exported as TypeScript types.

Reads go through `Cache::flexible()` per environment and key, for `PODS_CACHE_TTL` seconds (default 5), and are served stale for a little longer while they refresh. Writes clear the keys they affect, so the next page load or poll shows the new state. Lifecycle commands use `--no-block`, so a request returns right away and the page polls until systemd is done.

The golden JSON for every command lives in `tests/Fixtures/lpod/contract-1/`. The same files are in lpod's `tests/fixtures/contract-1/`, and they must stay identical: they are the spec for both sides.

## Applications and Quadlets

An application is what `podman quadlet list` groups together: one or more `.container`, `.pod`, `.kube`, `.volume` and `.network` units. Its main unit is the pod, otherwise the kube, otherwise the container with the shortest name. Its status comes from systemd (`ActiveState`, `SubState`, `Result`).

Installing an application writes Quadlet files on the host through lpod: from files you paste, or fetched from a Git repository at a reference and path. Pods first asks lpod to check them (`QuadletCheckController`), creates the secrets they need, then installs and optionally starts them.

Pods doesn't keep a copy of the units, containers or volumes. The host is the source of truth, and anything you change with `lpod` or `podman` in a terminal shows up in Pods.

For each application it installs, Pods does keep an installation (`src/Domain/Applications/Models/Installation.php`): the repository, reference, path and commit the files came from, the `pods.yml` as fetched (parsed again when read, so new manifest keys need no migration), and a sha256 checksum of each file. Syncing (`SyncInstallation`) compares those checksums with `lpod APP files --json` and keeps the files modified, added or removed outside Pods, without changing anything on the host. Drop-ins lpod writes itself (`*.d/lpod-*.conf`, such as on-demand's) don't count. The application page syncs each time it loads, and `pods:sync` syncs every environment at 01:00 UTC, when an on-demand Pods wakes its scheduler; it also forgets applications that are gone from the host. "Keep these files" on the application page accepts the files as they are, and starts tracking an application installed outside Pods. Editing a file in Pods only updates that file's checksum. The installation also keeps the exit code and time of each `after_install` step that ran (`RunSetupStep`, through `lpod container NAME exec`), never its inputs. Updating (`UpdateApplication`) installs another tag of the stored repository at the stored path over the application's files, and keeps that tag as the new reference.

## Web layer

Each page is a module in `src/Modules/Web` with an Inertia controller and a Vue page in `resources/js/pages`. Host routes are nested under `/environments/{environment}` in `routes/web.php` (pages) and `routes/actions.php` (actions), so several tabs can stay on different hosts. Slow host data is loaded with deferred props and skeletons, and status pages poll.

## Users

Pods has a single owner (the `super-admin` role) for now. Registration is off. Invited users with roles per environment are planned; see [roadmap.md](roadmap.md).

## Adding a host feature

1. Add the command to lpod first, with `--json` output that carries `contract`, and add its fixture to lpod's `tests/fixtures/contract-1/`.
2. Copy the fixture to `tests/Fixtures/lpod/contract-1/` here.
3. Add a method to `LpodClient`, a data object with `fromLpod()`, and cache keys to read or clear.
4. Add the controller under `src/Modules/Web`, the route under `/environments/{environment}`, and the page.
5. Test it with `fakeLpod()` and assert the exact lpod arguments with `assertRan()`.
