#!/usr/bin/env bash
set -euo pipefail

TMPDIR="/tmp/package-update"

: "${DB_NAME:?DB_NAME not set}"
: "${DB_USER:?DB_USER not set}"
: "${DB_HOST:?DB_HOST not set}"
: "${DB_PASS:?DB_PASS not set}"

echo "==== Starting package database update at $(date) ===="

psql_safe() {
    PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -Atq "$@"
}

rm -rf "$TMPDIR"
mkdir -p "$TMPDIR"

REPO_URLS=(
    "https://mirrors.neoarchlinux.org/neoarch/matrix/os/x86_64/matrix.db"
    "https://artix.sakamoto.pl/system/os/x86_64/system.db"
    "https://artix.sakamoto.pl/world/os/x86_64/world.db"
    "https://artix.sakamoto.pl/galaxy/os/x86_64/galaxy.db"
    "https://artix.sakamoto.pl/lib32/os/x86_64/lib32.db"
    "https://arch.sakamoto.pl/core/os/x86_64/core.db"
    "https://arch.sakamoto.pl/extra/os/x86_64/extra.db"
    "https://arch.sakamoto.pl/multilib/os/x86_64/multilib.db"
)

for REPO_URL in "${REPO_URLS[@]}"; do
    echo "[INFO] Downloading $REPO_URL"
    curl -fsSL "$REPO_URL" -o "$TMPDIR/$(basename "$REPO_URL")"
done

declare -A CACHE

echo "[INFO] Building package cache from DB..."
while IFS='|' read -r key _; do
    CACHE["$key"]=1
done < <(psql_safe <<'SQL'
SELECT repo || '/' || name || '-' || version AS key
FROM package_meta;
SQL
)

echo "[INFO] Cache contains ${#CACHE[@]} packages"

for DBFILE in "$TMPDIR"/*.db; do
    REPO_NAME="$(basename "$DBFILE" .db)"

    echo "[INFO] Processing $REPO_NAME"

    EXTRACT_DIR="$TMPDIR/$REPO_NAME"

    mkdir -p "$EXTRACT_DIR"
    tar -xf "$DBFILE" -C "$EXTRACT_DIR"

    for DESC in "$EXTRACT_DIR"/*/desc; do
        [ -f "$DESC" ] || continue

        PKG_NAME=$(awk '/%NAME%/{getline; print}' "$DESC")
        PKG_VER=$(awk '/%VERSION%/{getline; print}' "$DESC")

        CACHE_KEY="$REPO_NAME/$PKG_NAME-$PKG_VER"
        if [[ -n "${CACHE[$CACHE_KEY]:-}" ]]; then
            continue
        fi

        PKG_DESC=$(awk '/%DESC%/{getline; print}' "$DESC")
        PKG_URL=$(awk '/%URL%/{getline; print}' "$DESC")

        echo "[INFO] Upserting package $REPO_NAME/$PKG_NAME"

        psql_safe \
            --set=name="$PKG_NAME" \
            --set=repo="$REPO_NAME" \
            --set=ver="$PKG_VER" \
            --set=desc="$PKG_DESC" \
            --set=url="$PKG_URL" <<'SQL'
INSERT INTO package_meta (name, repo, version, description, url, last_updated)
VALUES (:'name', :'repo', :'ver', :'desc', :'url', CURRENT_DATE)
ON CONFLICT (name, repo) DO UPDATE
SET
    version = EXCLUDED.version,
    description = EXCLUDED.description,
    url = EXCLUDED.url,
    last_updated = EXCLUDED.last_updated;
SQL
    done
done

for DBFILE in "$TMPDIR"/*.db; do
    REPO_NAME="$(basename "$DBFILE" .db)"
    EXTRACT_DIR="$TMPDIR/$REPO_NAME"

    for DESC in "$EXTRACT_DIR"/*/desc; do
        [ -f "$DESC" ] || continue

        PKG_NAME=$(awk '/%NAME%/{getline; print}' "$DESC")
        PKG_VER=$(awk '/%VERSION%/{getline; print}' "$DESC")

        CACHE_KEY="$REPO_NAME/$PKG_NAME-$PKG_VER"
        if [[ -n "${CACHE[$CACHE_KEY]:-}" ]]; then
            continue
        fi

        PKG_ID=$(psql_safe \
            --set=name="$PKG_NAME" \
            --set=repo="$REPO_NAME" <<'SQL'
SELECT id FROM package_meta WHERE name = :'name' AND repo = :'repo';
SQL
)

        if [[ -z "$PKG_ID" ]]; then
            echo "[ERROR] Package $REPO_NAME/$PKG_NAME not found in package_meta"
            exit 1
        fi

        echo "[INFO] Processing relations for $REPO_NAME/$PKG_NAME"

        # Remove all old relations first
        psql_safe --set=pkg_id="$PKG_ID" <<'SQL'
DELETE FROM package_relations WHERE package_id = :'pkg_id';
SQL

        insert_component_and_relation() {
            local name="$1"
            local type="$2"
            local version="$3"
            local desc="$4"

            psql_safe \
                --set=name="$name" \
                --set=pkg_id="$PKG_ID" \
                --set=type="$type" \
                --set=ver="$version" \
                --set=desc="$desc" <<'SQL'
WITH comp AS (
    INSERT INTO components (name, is_virtual)
    VALUES (
        :'name',
        NOT EXISTS (SELECT 1 FROM package_meta WHERE name = :'name')
    )
    ON CONFLICT (name) DO UPDATE
        SET is_virtual = NOT EXISTS (SELECT 1 FROM package_meta WHERE name = EXCLUDED.name)
    RETURNING id
)
INSERT INTO package_relations
(package_id, component_id, relation_type, version_expr, relation_description)
VALUES
(
    :'pkg_id',
    (SELECT id FROM comp),
    :'type',
    NULLIF(:'ver',''),
    NULLIF(:'desc','')
)
ON CONFLICT (package_id, component_id, relation_type, version_expr) DO NOTHING;
SQL
        }

        parse_block() {
            local block="$1"
            local type="$2"

            awk "/%$block%/{flag=1; next} /^%/{flag=0} flag" "$DESC" | while read -r line; do
                [ -z "$line" ] && continue

                local name="$line"
                local version=""
                local desc=""

                if [[ "$type" == "OPTDEPENDS" && "$line" == *:* ]]; then
                    name="${line%%:*}"
                    desc="${line#*: }"
                fi

                if [[ "$name" =~ ^([^<>=]+)([<>=].*)$ ]]; then
                    name="${BASH_REMATCH[1]}"
                    version="${BASH_REMATCH[2]}"
                fi

                insert_component_and_relation "$name" "$type" "$version" "$desc"
            done
        }

        # implicit self-provide
        insert_component_and_relation "$PKG_NAME" "PROVIDES" "" ""

        parse_block DEPENDS DEPENDS
        parse_block OPTDEPENDS OPTDEPENDS
        parse_block MAKEDEPENDS MAKEDEPENDS
        parse_block CHECKDEPENDS CHECKDEPENDS
        parse_block PROVIDES PROVIDES
        parse_block CONFLICTS CONFLICTS
        parse_block REPLACES REPLACES
    done
done

rm -rf "$TMPDIR"

echo "==== Package database update complete at $(date) ===="
