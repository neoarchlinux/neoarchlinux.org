#!/usr/bin/env bash
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
path="$BASE_TMP/$repo/$pkg/$file"

firstline="$(head -n1 "$path" 2>/dev/null || true)"
shebang=$(printf '%s\n' "$firstline" | sed 's/^#![[:space:]]*//')

set -- $shebang

interp=""

if [ "$1" = "/usr/bin/env" ] || [ "$1" = "/bin/env" ] || [ "$1" = "env" ]; then
    shift

    if [ "$1" = "-S" ]; then
        shift
    fi

    interp="$1"
else
    interp=$(basename -- "$1")
fi

interp=$(printf '%s\n' "$interp" | sed 's/[[:space:]].*//')

psql_safe \
    --set=file_id="$file_id" \
    --set=interp="$interp" <<'SQL'
INSERT INTO package_file_script (file_id, script_executable)
VALUES (:file_id, NULLIF(:'interp', ''))
ON CONFLICT (file_id) DO NOTHING;
SQL
