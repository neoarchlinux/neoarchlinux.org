# install - Install packages

```bash
napm install <package> [<package>...]
```

- NAPM detects your active init system (`openrc`, `systemd`, `runit`, `s6`, or `dinit`) and checks whether init-system-specific companion packages exist for each package you are installing (e.g. `networkmanager-openrc`). If they exist, you are prompted to install them alongside the main package.

- NAPM provides automatic error resolution. Since `libalpm` operations often fail, NAPM automatically resolves many errors that may happen, e.g. if the package download fails with a stale-database error (HTTP 404 from a mirror), NAPM automatically:

    1. Removes the stale sync databases.
    2. Runs `napm update` to refresh metadata.
    3. Runs `napm upgrade` to bring the system up to date.
    4. Retries the original install.
