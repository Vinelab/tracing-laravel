FROM php:8.0-cli-alpine

ARG PUID=1000
ARG PGID=1000

RUN apk add --no-cache --virtual .build-deps \
        # for extensions
        $PHPIZE_DEPS \
    && \
    apk add --no-cache \
        bash \
        # for composer
        unzip \
    && \
    docker-php-ext-install \
        # for php-amqplib
        bcmath \
    && \
    apk del .build-deps

COPY --from=composer /usr/bin/composer /usr/bin/composer

# Add a non-root user to prevent files being created with root permissions on host machine.
RUN addgroup -g ${PGID} user && \
    adduser -u ${PUID} -G user -D user

WORKDIR /var/www/html

USER user
