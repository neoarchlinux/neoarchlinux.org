FROM alpine:3.20

RUN apk add --no-cache \
    bash \
    curl \
    tar \
    postgresql-client \
    coreutils \
    ca-certificates

RUN mkdir -p \
    /usr/local/bin \
    /etc/crontabs \
    /tmp/package-update

COPY scripts/update-packages.sh /usr/local/bin/update-packages.sh
RUN chmod +x /usr/local/bin/update-packages.sh

RUN echo "0 2 * * * /usr/local/bin/update-packages.sh" \
    > /etc/crontabs/root

RUN chmod 600 /etc/crontabs/root

CMD ["crond", "-f", "-l", "2"]
