#!/usr/bin/bash

set -euo pipefail

GNUPG_DIR="/srv/http/.gnupg"

mkdir -p "$GNUPG_DIR"

if id http >/dev/null 2>&1; then
  chown -R http:http "$GNUPG_DIR" || true
fi

chmod 700 "$GNUPG_DIR" || true

export GNUPGHOME="$GNUPG_DIR"

exec "$@"