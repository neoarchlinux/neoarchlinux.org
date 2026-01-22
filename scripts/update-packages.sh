#!/usr/bin/env bash
set -euo pipefail

TMPDIR="/tmp/package-update"

echo "==== Starting package database update at $(date) ===="

if [ -z ${DB_NAME+x} ]; then echo "DB_NAME not set"; exit 1; fi
if [ -z ${DB_USER+x} ]; then echo "DB_USER not set"; exit 1; fi
if [ -z ${DB_HOST+x} ]; then echo "DB_HOST not set"; exit 1; fi
if [ -z ${DB_PASS+x} ]; then echo "DB_PASS not set"; exit 1; fi

rm -rf "$TMPDIR"
mkdir -p "$TMPDIR"

REPO_URLS=(
    "https://mirrors.neoarchlinux.org/neoarch/matrix/os/x86_64/matrix.db"
    "https://artix.sakamoto.pl/system/os/x86_64/system.db"
    "https://artix.sakamoto.pl/world/os/x86_64/world.db"
    "https://artix.sakamoto.pl/galaxy/os/x86_64/galaxy.db"
    "https://artix.sakamoto.pl/lib32/os/x86_64/lib32.db"
    "https://arch.sakamoto.pl/extra/os/x86_64/extra.db"
    "https://arch.sakamoto.pl/multilib/os/x86_64/multilib.db"
)

for REPO_URL in "${REPO_URLS[@]}"; do
    echo "[INFO] Downloading $REPO_URL"
    curl -fsSL "$REPO_URL" -o "$TMPDIR/$(basename $REPO_URL)"
done

echo "[INFO] Extracting and parsing packages"

for DBFILE in "$TMPDIR"/*.db; do
    echo "[INFO] Processing $DBFILE"
    
    EXTRACT_DIR="$TMPDIR/$(basename $DBFILE .db)"
    mkdir -p "$EXTRACT_DIR"
    
    tar -xf "$DBFILE" -C "$EXTRACT_DIR"

    for DESC in "$EXTRACT_DIR"/*/desc; do
        [ -f "$DESC" ] || continue

        echo "[INFO] Parsing $DESC"

        PKG_NAME=$(awk '/%NAME%/{getline; print}' "$DESC")
        PKG_VER=$(awk '/%VERSION%/{getline; print}' "$DESC")
        PKG_DESC=$(awk '/%DESC%/{getline; print}' "$DESC")
        PKG_ARCH=$(awk '/%ARCH%/{getline; print}' "$DESC")
        REPO_NAME=$(basename "$DBFILE" .db)

        echo "[DEBUG] $PKG_NAME | $PKG_VER | $PKG_ARCH | $REPO_NAME"

        PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" \
  --set=name="$PKG_NAME" \
  --set=desc="$PKG_DESC" \
  --set=repo="$REPO_NAME" \
  --set=ver="$PKG_VER" \
  --set=arch="$PKG_ARCH" <<'SQL'
INSERT INTO package_meta (name, description, repo, version, arch, last_updated)
VALUES (:'name', :'desc', :'repo', :'ver', :'arch', CURRENT_DATE)
ON CONFLICT (name, repo) DO UPDATE
SET
  description = EXCLUDED.description,
  version = EXCLUDED.version,
  arch = EXCLUDED.arch,
  last_updated = EXCLUDED.last_updated;
SQL
    done
done
