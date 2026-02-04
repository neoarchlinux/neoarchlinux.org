#!/usr/bin/env bash
set -eu

: "${DB_NAME:?DB_NAME not set}"
: "${DB_USER:?DB_USER not set}"
: "${DB_HOST:?DB_HOST not set}"
: "${DB_PASS:?DB_PASS not set}"

psql_safe() {
    PGPASSWORD="$DB_PASS" psql -h "$DB_HOST" -U "$DB_USER" -d "$DB_NAME" -Atq "$@"
}

echo "==== Starting package files update at $(date) ===="

BASE_TMP="/tmp/package-updater/package-files"
MAN_DST="/app/man"

mkdir -p "$BASE_TMP"

extract_pkg() {
    pkgfile="$1"
    repo="$2"
    pkgname="$3"

    outdir="$BASE_TMP/$repo/$pkgname"
    mkdir -p "$outdir"

    tar -xf "$pkgfile" -C "$outdir"
}

insert_file_returning_id() {
    repo="$1"
    pkg="$2"
    file="$3"
    ftype="$4"

    mode=$(stat -c '%a' "$file" 2>/dev/null || echo 0)
    size=$(stat -c '%s' "$file" 2>/dev/null || echo 0)

    psql_safe \
        --set=repo="$repo" \
        --set=package="$pkg" \
        --set=file_path="$file" \
        --set=file_type="$ftype" \
        --set=file_mode="$mode" \
        --set=file_size="$size" <<'SQL'
    INSERT INTO package_files (
        package_id,
        file_path,
        file_type,
        file_mode,
        file_size
    )
    VALUES (
        (SELECT id FROM package_meta WHERE repo = :repo AND name = :package),
        '/' || :file_path,
        :file_type,
        :file_mode,
        :file_size
    )
    RETURNING id
SQL
}

classify_file() {
    repo="$1"
    pkg="$2"
    file="$3"

    filepath="$BASE_TMP/$repo/$pkg/$file"

    ftype="OTHER"

    if [ -L "$filepath" ]; then
        ftype="SYMLINK"
    else
        magic="$(file -b "$filepath")"

        case "$magic" in
            *empty*)             ftype="EMPTY" ;;
            *ELF*executable*)    ftype="ELFBIN" ;;
            *ELF*shared*object*) ftype="ELFLIB" ;;
            *script*)            ftype="SCRIPT" ;;
            *text*)              ftype="TEXT" ;;
            data)                ftype="DATA" ;;
        esac

        case "$file" in
            etc/*.conf|etc/*/*.conf)
                ftype="CONF"
                ;;
            usr/share/libalpm/hooks/*.hook)
                ftype="PACMAN_HOOK"
                ;;
        esac
    fi

    file_id="$(insert_file_returning_id "$repo" "$pkg" "$file" "$ftype")"

    case "$ftype" in
        ELFBIN)
            classify-elfbin.sh "$repo" "$pkg" "$file" "$file_id"
            ;;
        CONF)
            classify-conf.sh "$repo" "$pkg" "$file" "$file_id"
            ;;
        SCRIPT)
            classify-script.sh "$repo" "$pkg" "$file" "$file_id"
            ;;
        PACMAN_HOOK)
            classify-pacman-hook.sh "$repo" "$pkg" "$file" "$file_id"
            ;;
        SYMLINK)
            classify-symlink.sh "$repo" "$pkg" "$file" "$file_id"
            ;;
    esac

    case "$file" in
        usr/share/man/man1/*|usr/share/man/man8/*)
            section="$(basename "$(dirname "$file")")"
            mkdir -p "$MAN_DST/$repo/$pkg/$section"
            cp -Lf "$filepath" "$MAN_DST/$repo/$pkg/$section"
            ;;
    esac
}

handle_repo_file() {
    repo="$1"
    pkgfile="$2"

    [[ "$pkgfile" = *.sig ]] && return

    iden="$(basename "$pkgfile")"

    pkgname=$(psql_safe \
            --set=iden="$iden" \
            --set=repo="$repo" <<'SQL'
SELECT name FROM package_meta WHERE :iden LIKE name || '-' || version || '-%' AND repo = :repo
SQL
)

    extract_pkg "$pkgfile" "$repo" "$pkgname"

    find "$BASE_TMP/$repo/$pkgname" \( -type f -o -type l \) -printf '%P\n' | grep -v '^\.' | while read -r f; do
        classify_file "$repo" "$pkgname" "$f"
    done

    rm -rf "$BASE_TMP/$repo/$pkgname"
}

for repo in matrix; do
    for pkgfile in "/app/mirrors/neoarch/${repo}"/*.pkg.tar.*; do
        handle_repo_file $repo $pkgfile
    done
done

for repo in system world galaxy lib32; do
    for pkgfile in "/app/mirrors/artix/${repo}"/*.pkg.tar.*; do
        handle_repo_file $repo $pkgfile
    done
done

for repo in core extra multilib; do
    for pkgfile in "/app/mirrors/arch/${repo}"/*.pkg.tar.*; do
        handle_repo_file $repo $pkgfile
    done
done
