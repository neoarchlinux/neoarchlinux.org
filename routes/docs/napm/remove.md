# remove - Remove packages

```bash
napm remove <package> [<package>...]
napm remove --no-deep <package> [<package>...]
```

By default NAPM performs a deep removal: it also removes all packages that depend solely on the removed packages (equivalent to pacman's `-Rs` with cascade). Pass `--no-deep` to remove only the named packages.
