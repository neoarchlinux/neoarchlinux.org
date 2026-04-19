# NAPM - NeoArch Package Manager

NAPM is the NeoArch Package Manager, a Rust CLI built on top of `libalpm` (the same library that powers pacman). It provides a unified interface with automatic error recovery, an SQLite package cache for fast search and file lookups, and init-system awareness.

## Installation

NAPM is pre-installed on every NeoArch system. The binary is `napm` :

```bash
napm <command> [options]
```

## Commands

| Command | Description |
|---|---|
| napm [install](/napm/install) | Install one or more packages and their dependencies |
| napm [remove](/napm/remove) | Remove one or more packages |
| napm [upgrade](/napm/upgrade) | Upgrade all installed packages |
| napm [update](/napm/update) | Refresh package database metadata |
| napm [search](/napm/search) | Search for packages by name or description |
| napm [info](/napm/info) | Show metadata for a package |
| napm [files](/napm/files) | List the files that a package installs |
| napm [find](/napm/find) | Find which package owns a file |
| napm [list](/napm/list) | List all installed packages |

---

## Package cache

NAPM maintains an SQLite cache at `/var/cache/napm.sqlite` with two tables:

| Table | Contents |
|---|---|
| `package_desc` | Name, version, description, repo for every package in sync databases |
| `package_files` | All file paths for every package, indexed for fast lookups |

The cache is populated by `napm update --files`. On first run it is built from scratch from the `.files` archives in the pacman sync directory (`/var/lib/pacman/sync/`). Subsequent runs only process packages whose `name-version` identifier is not already cached, making incremental updates fast.

Repository priority for queries respects the order of `[repository]` sections in `pacman.conf` - earlier repositories win when the same package name appears in multiple repos.

---

## Auto-repair

NAPM intercepts `libalpm` errors and attempts automatic recovery before surfacing them to the user.

| Error | Automatic action |
|---|---|
| Handle lock (`db.lck`) | Checks for running `napm`/`pacman` processes. If none are found, removes the stale lock file. |
| Retrieve failure (stale mirror/404) | Removes sync databases, runs `napm update` + `napm upgrade`, then retries. |
| Unsatisfied dependencies | Reports the missing dependency and the package that requires it, then exits. |
| Conflicting packages | Reports the conflicting pair, then exits. |
| File conflicts | Reports the conflicting packages, then exits. |

With many more coming in the future.

---

## Init-system awareness

When installing packages, NAPM detects the active init system by checking which of `openrc`, `systemd`, `runit`, `s6`, or `dinit` is present in the local package database. For each package being installed, NAPM looks up whether a `<package>-<init>` variant exists in the sync databases and, if so, offers to install it automatically.

For example, installing `networkmanager` on an OpenRC system will prompt:

```
ACT: Do you want to install networkmanager-openrc - OpenRC init scripts for NetworkManager? [Y/n]:
```

---

## Error handling and exit codes

NAPM exits with code 0 on success. On error it prints a fatal message prefixed with `F:` and exits with code 1. The special `NothingToDo` condition (no packages to upgrade, etc.) exits with code 0 and prints an informational message.

---

## Configuration

NAPM reads standard pacman configuration from `/etc/pacman.conf` via `pacmanconf`. All repository definitions, cache directories, signature levels, parallel download settings, and hook directories are honoured automatically - no separate NAPM configuration file is needed.
