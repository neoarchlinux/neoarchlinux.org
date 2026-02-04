#!/usr/bin/bash
set -eu

: "${DB_NAME:?DB_NAME not set}"
: "${DB_USER:?DB_USER not set}"
: "${DB_HOST:?DB_HOST not set}"
: "${DB_PASS:?DB_PASS not set}"

psql_safe() {
    PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -Atq "$@"
}

repo="$1"
pkg="$2"
file="$3"
file_id="$4"

BASE_TMP="/tmp/package-updater/package-files"
pkgroot="$BASE_TMP/$repo/$pkg"

confbase="$(basename "$file")"

cache="$pkgroot/.strings-cache"
mkdir -p "$cache"

find "$pkgroot/usr/bin" "$pkgroot/usr/local/bin" -type f 2>/dev/null | while read -r bin; do
    cachefile="$cache/$bin.strings"
    cachefiledir="$(dirname $cachefile)"

    mkdir -p "$cachefiledir"

    if [ ! -f "$cachefile" ]; then
        strings -a "$bin" > "$cachefile" || true
    fi

    if grep -q "$confbase" "$cachefile"; then
        psql_safe \
            --set=file_id="$file_id" \
            --set=user="$bin" <<'SQL'
INSERT INTO package_file_conf_users (file_id, conf_user)
VALUES (:file_id, '/' || :user)
ON CONFLICT DO NOTHING;
SQL
    fi
done
