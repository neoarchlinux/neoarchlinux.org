#!/usr/bin/env bash
set -eu

while true; do
    sync-repos.sh
    update-packages.sh
    update-files.sh

    sleep 3600
done