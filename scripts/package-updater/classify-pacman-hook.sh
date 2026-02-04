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

action_when=""
action_desc=""

reset_trigger() {
    trigger_type=""
    on_install=false
    on_upgrade=false
    on_remove=false
    trigger_targets=()
}

flush_trigger() {
    [ -z "$trigger_type" ] && return

    trigger_id=$(psql_safe \
        --set=file_id="$file_id" \
        --set=type="$trigger_type" \
        --set=ins="$on_install" \
        --set=upg="$on_upgrade" \
        --set=rem="$on_remove" <<'SQL'
INSERT INTO package_file_pacman_hook_triggers
(file_id, trigger_type, trigger_on_install, trigger_on_upgrade, trigger_on_remove)
VALUES (:file_id, :type, :ins, :upg, :rem)
RETURNING id;
SQL
)

    for tgt in "${trigger_targets[@]}"; do
        psql_safe \
            --set=tid="$trigger_id" \
            --set=tgt="$tgt" <<'SQL'
INSERT INTO pacman_hook_trigger_targets (file_id, trigger_target)
VALUES (:tid, :tgt)
ON CONFLICT DO NOTHING;
SQL
    done
}

reset_trigger
current_section=""

while IFS= read -r line; do
    case "$line" in
        \[*\])
            [ "$current_section" = "Trigger" ] && flush_trigger

            current_section="${line#[}"
            current_section="${current_section%]}"

            [ "$current_section" = "Trigger" ] && reset_trigger
            ;;
        *=*)
            key="${line%%=*}"
            val="${line#*=}"

            case "$current_section:$key" in
                Trigger:Type)
                    trigger_type="$val"
                    ;;
                Trigger:Operation)
                    case "$val" in
                        Install) on_install=true ;;
                        Upgrade) on_upgrade=true ;;
                        Remove)  on_remove=true ;;
                    esac
                    ;;
                Trigger:Path|Trigger:Target)
                    trigger_targets+=("$val")
                    ;;
                Action:When)
                    action_when="$val"
                    ;;
                Action:Description)
                    action_desc="$val"
                    ;;
            esac
            ;;
    esac
done < "$path"

[ "$current_section" = "Trigger" ] && flush_trigger

psql_safe \
    --set=file_id="$file_id" \
    --set=when="$action_when" \
    --set=desc="$action_desc" <<'SQL'
INSERT INTO package_file_pacman_hook
(file_id, action_when, action_description)
VALUES (:file_id, :when, NULLIF(:desc, ''))
ON CONFLICT (file_id) DO NOTHING;
SQL
