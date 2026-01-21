FROM artixlinux/artixlinux:base

RUN pacman -Syu --noconfirm \
    python \
    python-websockets \
    bash \
    dosfstools \
    e2fsprogs \
    libarchive \
    libisoburn \
    mtools \
    squashfs-tools \
    gnupg \
    grub \
    openssl \
    artix-archlinux-support \
 && pacman -Scc --noconfirm

RUN pacman-key --init && \
    pacman-key --populate archlinux

RUN sed -i \
        '/^\# An example of a custom package repository/i \
[extra]\nInclude = /etc/pacman.d/mirrorlist-arch\n' \
        /etc/pacman.conf

RUN sed -i \
        '/^\# An example of a custom package repository/i \
[multilib]\nInclude = /etc/pacman.d/mirrorlist-arch\n' \
        /etc/pacman.conf

RUN pacman -Syu --noconfirm arch-install-scripts erofs-utils

WORKDIR /app

COPY scripts/iso-builder.py .

COPY iso /iso

USER root

CMD ["python", "iso-builder.py"]
