FROM archlinux:latest

RUN pacman-key --init && \
    pacman-key --populate archlinux && \
    pacman -Sy --noconfirm archlinux-keyring && \
    pacman -Syu --noconfirm

RUN pacman -S --noconfirm \
      php \
      php-fpm \
      php-gd \
      php-intl \
      php-pgsql \
      php-sqlite \
      ca-certificates \
      curl \
      git \
      tar \
      gzip \
      xz \
      bash \
      pacman-contrib && \
    pacman -Scc --noconfirm

RUN mkdir -p /run/php

RUN sed -i "s|;daemonize = yes|daemonize = no|" /etc/php/php-fpm.conf

RUN sed -i "s|listen = /run/php-fpm/php-fpm.sock|listen = 9000|" /etc/php/php-fpm.d/www.conf && \
    sed -i "s|;clear_env = no|clear_env = no|" /etc/php/php-fpm.d/www.conf

RUN grep "user = http" /etc/php/php-fpm.d/www.conf && \
    grep "group = http" /etc/php/php-fpm.d/www.conf

RUN sed -i "s|;extension=pdo_pgsql|extension=pdo_pgsql|" /etc/php/php.ini && \
    sed -i "s|;extension=pg_trgm|extension=pg_trgm|" /etc/php/php.ini && \
    sed -i "s|;extension=pgsql|extension=pgsql|" /etc/php/php.ini

WORKDIR /var/www/html

EXPOSE 9000

COPY scripts/docker-php-entrypoint.sh /docker-entrypoint.sh
RUN chmod +x /docker-entrypoint.sh
ENTRYPOINT ["/docker-entrypoint.sh"]

CMD ["php-fpm", "-F"]
