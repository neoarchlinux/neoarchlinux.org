# find - Find which package owns a file

```bash
napm find <path>
napm find --exact <path>
```

Searches the file cache for packages that contain a file matching `<path>`. Without `--exact`, a suffix match is performed (e.g. `sudo` matches `/usr/bin/sudo`). With `--exact`, only exact paths match.

NAPM automatically redirects legacy paths: if you search for `/bin/sudo`, it searches for `/usr/bin/sudo` instead (since `/bin` is a symlink to `/usr/bin` on modern systems).
