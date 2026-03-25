# =============================================================================
# MediaFlow — Application Image
# Base: PHP 8.3-FPM (Debian Bookworm)
#
# Includes:
#   - PHP extensions: pdo_mysql, gd, imagick, redis, bcmath, zip, exif, pcntl
#   - Composer 2.9
#   - Node.js 24 (for Vite asset compilation inside the container)
#
# This image is shared by both the `app` (PHP-FPM) and `horizon` (queue worker)
# services. The service command determines what process runs at startup.
# =============================================================================

FROM php:8.3-fpm-bookworm

# -----------------------------------------------------------------------------
# System dependencies
# -----------------------------------------------------------------------------
# libmagickwand-dev  → required by the imagick PECL extension
# libpng-dev         → required by GD (PNG support)
# libjpeg62-turbo-dev → required by GD (JPEG support)
# libwebp-dev        → required by GD (WebP support)
# libfreetype6-dev   → required by GD (TrueType font support)
# libzip-dev         → required by the zip PHP extension
# unzip / git / curl → required by Composer and general tooling
RUN apt-get update && apt-get install -y --no-install-recommends \
    libmagickwand-dev \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    git \
    curl \
    && rm -rf /var/lib/apt/lists/*

# -----------------------------------------------------------------------------
# PHP extensions
# -----------------------------------------------------------------------------
# Core extensions — installed first, separately from GD to isolate failures.
# pcntl is required by Laravel Horizon for process signal handling.
RUN docker-php-ext-install \
        pdo_mysql \
        bcmath \
        zip \
        exif \
        pcntl \
        opcache \
        intl

# GD — configured and installed in its own step so any compile errors are
# explicit rather than masked by parallel builds. Requires libwebp-dev,
# libfreetype6-dev, and libjpeg-dev (all installed in the apt step above).
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install gd

# Imagick — superior image resampling quality over GD; required by spec (OQ-3)
# Installing via PECL. If this fails during the build, treat it as a blocker —
# do NOT fall back to GD. See PROJECT_SPEC.md § Open Questions OQ-3.
RUN pecl install imagick \
    && docker-php-ext-enable imagick

# Redis PHP client — required for QUEUE_CONNECTION=redis and Cache::store('redis')
RUN pecl install redis \
    && docker-php-ext-enable redis

# -----------------------------------------------------------------------------
# Composer 2.9
# -----------------------------------------------------------------------------
COPY --from=composer:2.9 /usr/bin/composer /usr/bin/composer

# -----------------------------------------------------------------------------
# Node.js 24 (LTS)
# Required inside the container for: npm install, npm run build (Vite)
# Not required on the host machine.
# -----------------------------------------------------------------------------
RUN curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# -----------------------------------------------------------------------------
# PHP runtime configuration
# -----------------------------------------------------------------------------
COPY docker/php/php.ini /usr/local/etc/php/conf.d/mediaflow.ini

# -----------------------------------------------------------------------------
# Entrypoint
# Handles storage permission setup before the main process starts.
# -----------------------------------------------------------------------------
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# -----------------------------------------------------------------------------
# Working directory
# The project root is bind-mounted here at runtime by docker-compose.
# -----------------------------------------------------------------------------
WORKDIR /var/www/html

ENTRYPOINT ["entrypoint.sh"]

# Default: run PHP-FPM. The `horizon` service overrides this to `php artisan horizon`.
CMD ["php-fpm"]
