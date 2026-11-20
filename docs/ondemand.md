---
title: On-demand Applications
section: Configuration
order: 3
tags:
    - ondemand
    - socket-activation
    - systemd
---

# On-demand applications

An application you don't use all day doesn't have to run all day. Turn on **On demand** on its page, and it sleeps until a request comes in. This is systemd socket activation, the way `cockpit.socket` works, so it needs no daemon of Pods' own and keeps working when Pods itself is off. lpod writes the units:

| Unit                                 | What it does                                                                                                                                                 |
| ------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `APP-ondemand.socket`                | Listens on the application's port, and starts the service below on the first connection                                                                      |
| `APP-ondemand.service`               | `systemd-socket-proxyd`: starts the application, forwards the connection to it, and exits after the idle timeout (10 minutes by default) without one         |
| A drop-in for the main unit          | Keeps it from starting at boot, and lets systemd stop it once the proxy is gone (`StopWhenUnneeded=yes`)                                                     |
| `APP-idle.timer`, `APP-idle.service` | Optional: once the application is asleep and an idle command finds no work left, stops its workers (such as a queue worker), so their services can sleep too |
| A timer for the scheduler            | Optional: runs a scheduler container only at the minutes a task is due, instead of every minute                                                              |

The first request after a sleep waits for the application to start, so it's best for applications that start in a few seconds. **Start** becomes **Wake** while an application sleeps, and **Stop** puts it to sleep. Pods suggests the ports, workers and scheduler from the Quadlet files, or from the `ondemand:` section of its [`pods.yml`](manifest.md), and everything stays editable.

**Behind the proxy**, the main route of an on-demand application goes to its socket, so opening its hostname wakes it. Routes with `via: main` go the same way. Other routes, such as an admin UI on a container of its own, get an error while the application sleeps, until it's woken another way. The proxy itself always runs.

The same units work without Pods, from a terminal: `lpod APP ondemand enable --port PORT --app-port PORT` (see `lpod APP ondemand` in lpod's `docs/commands.md`).
