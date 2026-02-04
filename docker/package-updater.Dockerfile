FROM alpine:3.20

RUN apk add --no-cache \
    bash \
    curl \
    tar \
    postgresql-client \
    coreutils \
    ca-certificates \
    rsync \
    grep \
    sed \
    findutils \
    file \
    binutils

RUN mkdir -p \
    /usr/local/bin \
    /tmp/package-update

COPY scripts/package-updater/* /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/sync-repos.sh
RUN chmod +x /usr/local/bin/update-packages.sh
RUN chmod +x /usr/local/bin/update-files.sh
RUN chmod +x /usr/local/bin/classify-conf.sh
RUN chmod +x /usr/local/bin/classify-elfbin.sh
RUN chmod +x /usr/local/bin/classify-pacman-hook.sh
RUN chmod +x /usr/local/bin/classify-script.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
