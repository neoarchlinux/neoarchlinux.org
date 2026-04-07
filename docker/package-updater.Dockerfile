FROM archlinux:latest AS builder

RUN pacman -Syu --noconfirm --needed \
    rust \
    cargo \
    base-devel

WORKDIR /build

COPY package-updater/Cargo.toml .

RUN mkdir src && echo "fn main(){}" > src/main.rs
RUN cargo build --release

RUN rm -rf src target
COPY package-updater/src ./src

RUN cargo build --release

#################################################################

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
