#!/usr/bin/env bash

ARCH_DST="/app/mirrors/arch"
ARCH_URL="rsync://mirror.pseudoform.org/packages/"

ARTIX_DST="/app/mirrors/artix"
ARTIX_URL="rsync://ftp.sh.cvut.cz/artix-linux/"

RSYNC_CMD="rsync -vrulhpthP --delete-delay --delay-updates --no-motd"

for repo in core extra multilib pool lastsync; do
    $RSYNC_CMD $ARCH_URL$repo $ARCH_DST
done

for repo in galaxy lib32 system world lastsync; do
    $RSYNC_CMD $ARTIX_URL$repo $ARTIX_DST
done
