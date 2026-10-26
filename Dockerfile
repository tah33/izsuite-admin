# ─── Base ─────────────────────────────────────────────────────────────────────
# FrankenPHP: Caddy with PHP embedded, so the container speaks HTTP directly on
# a port instead of FastCGI. That matters here — the host's nginx is a plain
# reverse proxy, and proxy_pass to an HTTP port avoids the php-fpm arrangement
# where the project must sit at an identical absolute path on both sides or
# every request 404s on SCRIPT_FILENAME.
#
# Classic mode, NOT Octane: laravel/octane is not in composer.json. Adding it
# would mean editing a dependency set owned by another repo, plus auditing the
# app for state that leaks between requests. If Octane is wanted later it is a
# one-line change in docker/entrypoint.sh.
#
# Pinned to an exact PHP patch rather than the floating `1-php8.4` tag: PHP
# 8.4.27 (21 Oct 2026) fixes CVE-2026-109618 and CVE-2026-109619, two
# unauthenticated remote code execution flaws in PHP core, both CVSS 9.2. With a
# floating tag a build on a host whose image cache predates the release quietly
# ships the vulnerable 8.4.26, and nothing in the build output says so. Bump
# this deliberately at the next security release.
FROM dunglas/frankenphp:1-php8.4.27 AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    gosu \
    curl \
    unzip \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    libonig-dev \
    && rm -rf /var/lib/apt/lists/*

# Node 22 for the Vite/Tailwind 4 build. Present in base because the dev target
# needs it at runtime; the production target keeps it only because assets are
# compiled in the builder stage and copied across.
#
# NODE_MIN and PHP_MIN are the 21 Oct 2026 security releases, asserted rather
# than pinned. NodeSource serves the newest 22.x, so the install normally lands
# on 22.23.4 or later; an exact deb version pin would break the build whenever
# NodeSource prunes that package, while a stale apt mirror handing back an older
# nodejs would otherwise ship the Brotli decoder out-of-bounds write
# (CVE-2026-111497) and two TLS hostname bypasses without a word in the log.
# The PHP assertion guards the base image tag above the same way, so reverting
# that tag to a floating one cannot silently reintroduce the PHP core RCEs.
ARG NODE_MIN=22.23.4
ARG PHP_MIN=8.4.27
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/* \
    && node_have="$(node -v | tr -d v)" \
    && [ "$(printf '%s\n%s\n' "$NODE_MIN" "$node_have" | sort -V | head -1)" = "$NODE_MIN" ] \
        || { echo "==> Node $node_have predates the $NODE_MIN security release"; exit 1; } \
    && php_have="$(php -r 'echo PHP_VERSION;')" \
    && [ "$(printf '%s\n%s\n' "$PHP_MIN" "$php_have" | sort -V | head -1)" = "$PHP_MIN" ] \
        || { echo "==> PHP $php_have predates the $PHP_MIN security release"; exit 1; }

# Refresh install-php-extensions before use: the copy baked into the base image
# is older than the release below.
#
# phpredis is built from a git clone of its release tag, not from PECL. The
# installer resolves `redis` through PECL's REST API, which intermittently
# fails with "does not have REST xml/dependency information available" or "No
# releases available" (its CDN answers 504 after 20s) and takes the whole build
# down with it. A clone rather than the installer's `phpredis/phpredis@<tag>`
# shorthand: that fetches GitHub's tag archive, which omits the liblzf
# submodule package.xml lists, so the install fails. Bump the tag deliberately
# when upgrading.
#
# Extension set is driven by composer.json and the code:
#   pdo_mysql          DB_CONNECTION=mysql
#   zip                "ext-zip": "*" is a hard require
#   gd                 barryvdh/laravel-dompdf image rendering
#   mbstring, intl     smalot/pdfparser text extraction, timezones.json
#   bcmath             money maths in the billing tables
#   exif               image uploads
#   redis              cache/session/queue once moved off the database driver
#   opcache            production bytecode cache
#   pcntl              graceful SIGTERM handling in queue:work
RUN curl -sSLf https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
        -o /usr/local/bin/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    && git -c advice.detachedHead=false clone --quiet --depth 1 --branch 6.3.0 \
        --recurse-submodules --shallow-submodules \
        https://github.com/phpredis/phpredis.git /tmp/phpredis \
    && install-php-extensions \
        pdo_mysql \
        zip \
        gd \
        bcmath \
        intl \
        exif \
        mbstring \
        /tmp/phpredis \
        opcache \
        pcntl \
    && rm -rf /tmp/phpredis

COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/99-uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_MEMORY_LIMIT=-1 \
    COMPOSER_HOME=/tmp/composer \
    # Listen on 8000 over plain HTTP. A bare port (no hostname) tells Caddy to
    # skip automatic TLS — the host nginx terminates it, and Let's Encrypt
    # inside a container with no public DNS name would fail anyway.
    SERVER_NAME=":8000"

WORKDIR /app

RUN git config --system --add safe.directory /app \
    && mkdir -p \
        /tmp/composer \
        /var/www/.cache/composer \
        storage/app/public \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data \
        /tmp/composer \
        /var/www/.cache/composer \
        /app \
        /data \
        /config

# ─── Development ──────────────────────────────────────────────────────────────
# Runs as root deliberately: source arrives via bind-mount owned by the host
# user, and a mismatched container UID makes storage/ unwritable. Acceptable in
# a local container that never faces the internet.
FROM base AS development

ENV APP_ENV=local \
    APP_DEBUG=true

COPY composer.json composer.lock* ./
# composer.lock is gitignored in this repo, so it is usually absent and this
# resolves fresh versions — meaning two builds of the same commit can differ.
# See the note in README/deploy docs; committing the lockfile fixes it.
RUN php -d memory_limit=-1 /usr/bin/composer install --no-scripts --no-autoloader --no-interaction

COPY package.json package-lock.json* ./
# `npm ci` requires a lockfile and hard-fails without one, so fall back to
# `install` and say so loudly rather than breaking the build.
#
# Retry-hardened: the build network intermittently resets large registry
# downloads (ECONNRESET) even when the host is fine — same problem, same fix as
# the wwp-docker image.
RUN npm config set fetch-retries 10 \
    && npm config set fetch-retry-mintimeout 2000 \
    && npm config set fetch-retry-maxtimeout 120000 \
    && npm config set fetch-timeout 600000 \
    && if [ -f package-lock.json ]; then \
        (npm ci || (sleep 10 && npm ci) || (sleep 30 && npm ci)); \
    else \
        echo "==> WARN: no package-lock.json — falling back to 'npm install' (non-reproducible)"; \
        (npm install || (sleep 10 && npm install) || (sleep 30 && npm install)); \
    fi

COPY . .

# --no-scripts is required, not cosmetic: composer.json wires
# `php artisan package:discover` to post-autoload-dump, which boots the full
# framework. At build time there is no .env and no database, so it exits 1 and
# fails the image. The entrypoint runs package:discover at container start,
# where the environment actually exists.
RUN php -d memory_limit=-1 /usr/bin/composer dump-autoload --no-scripts

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["/entrypoint.sh"]

# ─── Builder (compile frontend assets) ────────────────────────────────────────
FROM base AS builder

COPY package.json package-lock.json* ./
# See the development stage for why the retry wrapper is here.
RUN npm config set fetch-retries 10 \
    && npm config set fetch-retry-mintimeout 2000 \
    && npm config set fetch-retry-maxtimeout 120000 \
    && npm config set fetch-timeout 600000 \
    && if [ -f package-lock.json ]; then \
        (npm ci || (sleep 10 && npm ci) || (sleep 30 && npm ci)); \
    else \
        echo "==> WARN: no package-lock.json — falling back to 'npm install' (non-reproducible)"; \
        (npm install || (sleep 10 && npm install) || (sleep 30 && npm install)); \
    fi

COPY . .

# Vite needs the PHP side present for laravel-vite-plugin to resolve paths, so
# this runs after the full COPY rather than against package.json alone.
RUN npm run build

# ─── Production ───────────────────────────────────────────────────────────────
FROM base AS production

ENV APP_ENV=production \
    APP_DEBUG=false

# Default to --no-dev. Staging can pass --build-arg INCLUDE_DEV_DEPS=true to
# get require-dev packages; gating at build time means there is no env var that
# can switch dev tooling on in a live image, because the classes are not there.
ARG INCLUDE_DEV_DEPS=false
COPY composer.json composer.lock* ./
RUN if [ "$INCLUDE_DEV_DEPS" = "true" ]; then \
        composer install --no-scripts --no-autoloader --no-interaction; \
    else \
        composer install --no-dev --no-scripts --no-autoloader --no-interaction; \
    fi

COPY . .
COPY --from=builder /app/public/build ./public/build

# --no-scripts for the same reason as the development stage: the
# post-autoload-dump hook boots Laravel, which cannot work without a .env.
RUN php -d memory_limit=-1 /usr/bin/composer dump-autoload --optimize --no-scripts \
    && chown -R www-data:www-data /app

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8000

# Starts as root so the entrypoint can repair ownership on named volumes, then
# drops the server process to www-data before serving anything.
ENTRYPOINT ["/entrypoint.sh"]
