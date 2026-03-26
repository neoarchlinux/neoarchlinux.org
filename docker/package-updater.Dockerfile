# FROM alpine:3.20

# RUN apk add --no-cache \
#     bash \
#     curl \
#     tar \
#     postgresql-client \
#     coreutils \
#     ca-certificates \
#     rsync \
#     grep \
#     sed \
#     findutils \
#     file \
#     binutils \
#     zstd \
#     xz

# RUN mkdir -p \
#     /usr/local/bin \
#     /tmp/package-update

# COPY scripts/package-updater/* /usr/local/bin/
# RUN chmod +x /usr/local/bin/docker-entrypoint.sh
# RUN chmod +x /usr/local/bin/sync-repos.sh
# RUN chmod +x /usr/local/bin/update-packages.sh
# RUN chmod +x /usr/local/bin/update-files.sh
# RUN chmod +x /usr/local/bin/classify-conf.sh
# RUN chmod +x /usr/local/bin/classify-elfbin.sh
# RUN chmod +x /usr/local/bin/classify-pacman-hook.sh
# RUN chmod +x /usr/local/bin/classify-script.sh
# RUN chmod +x /usr/local/bin/classify-symlink.sh

# ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

FROM archlinux:latest AS builder

RUN pacman -Syu --noconfirm --needed \
    rust \
    cargo \
    base-devel

WORKDIR /build

COPY package-updater/Cargo.toml .
RUN mkdir src && echo "fn main(){}" > src/main.rs
RUN cargo build --release
RUN rm -rf src

COPY package-updater/src ./src

RUN cargo build --release

#################################################################3

FROM archlinux:latest

RUN pacman -Syu --noconfirm --needed \
    bash \
    rsync \
    tar \
    findutils \
    file \
    binutils

COPY --from=builder /build/target/release/package-updater /usr/local/bin/

ENTRYPOINT ["package-updater"]
