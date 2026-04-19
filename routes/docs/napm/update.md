# update - Update the system

WARNING: **This is not a [system upgrade](/napm/upgrade).**

```bash
napm update
napm update --files
```

Without `--files`, only the package databases (`.db`) are updated. With `--files`, the file databases (`.files`) are also updated - these are needed for `napm find` and `napm files` to work with packages not yet cached locally.
