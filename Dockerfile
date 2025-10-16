# Default PHP version (can be overridden in compose files)
ARG PHP_VERSION=8.3

# =============================================================================
# Base Stage - Common dependencies and setup
# =============================================================================
FROM webdevops/php-apache:${PHP_VERSION} AS base

# Accept build arguments for customization
ARG PROJECT_NAME=drupal-site
ARG ENVIRONMENT=development

# Set environment variables
ENV WEB_DOCUMENT_ROOT="/app/web"
ENV WEB_DOCUMENT_INDEX="index.php"
ENV APPLICATION_PATH="/app"
ENV PROJECT_NAME=${PROJECT_NAME}

# Install common tools needed for Drupal
# CUSTOMIZATION: Add any additional packages your project needs here
RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    mariadb-client \
    vim \
    wget \
    curl \
    nano \
    libmemcached-dev \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /app

# =============================================================================
# Development Stage
# =============================================================================
FROM base AS development

# Development-friendly PHP settings (can be overridden via environment variables)
ENV PHP_DISPLAY_ERRORS=1
ENV PHP_MEMORY_LIMIT=512M
ENV PHP_MAX_EXECUTION_TIME=300
ENV PHP_OPCACHE_ENABLE=0
ENV ENVIRONMENT=development

# Copy application files with writable permissions
COPY --chown=application:application . /app

# Install Composer dependencies with dev packages
RUN composer install --no-interaction --optimize-autoloader

RUN ln -s /app/vendor/drush/drush/drush /usr/local/bin/drush

RUN mkdir -p /app/web/sites/default/files \
    /app/config \
    /app/private

EXPOSE 80

# =============================================================================
# Production Stage
# =============================================================================
FROM base AS production

# Production PHP settings (can be overridden by environment variables)
# Default values - will be overridden by compose file env_file
ENV PHP_DISPLAY_ERRORS=0
ENV PHP_MEMORY_LIMIT=512M
ENV PHP_MAX_EXECUTION_TIME=300
ENV PHP_OPCACHE_ENABLE=1
ENV PHP_OPCACHE_MEMORY_CONSUMPTION=256
ENV PHP_OPCACHE_MAX_ACCELERATED_FILES=20000
ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=0
ENV ENVIRONMENT=production

# Copy application files
COPY --chown=application:application . /app
COPY ./config /app/config

# Install Composer dependencies WITHOUT dev packages
RUN composer install --no-interaction --optimize-autoloader --no-dev \
    && composer clear-cache \
    && ln -s /app/vendor/drush/drush/drush /usr/local/bin/drush

# Make Drush executable and create symlink (must be before setting read-only permissions)
# RUN chmod +x /app/vendor/drush/drush/drush \
#     && ln -s /app/vendor/drush/drush/drush /usr/local/bin/drush \
#     && chmod +x /usr/local/bin/drush

# Create necessary directories (including PHP storage and temp)
RUN mkdir -p /app/web/sites/default/files \
    /app/web/sites/default/files/php \
    /app/web/sites/default/files/css \
    /app/web/sites/default/files/js \
    /app/config \
    /app/private \
    /tmp

# Set permissions for writable directories in production
RUN chown -R application:application /app/web/sites/default/files \
    && chmod -R 775 /app/web/sites/default/files \
    && chown -R application:application /app/private \
    && chmod -R 775 /app/private \
    && chown -R application:application /tmp \
    && chmod -R 1777 /tmp

# Make application code read-only (except writable directories)
# Keep these writable: files, private, config, PHP storage, vendor/drush
RUN find /app -type f -not -path "/app/web/sites/default/files/*" \
    -not -path "/app/private/*" \
    -not -path "/app/config/*" \
    -not -path "/app/vendor/drush/*" \
    -not -path "/app/web/sites/simpletest/*" \
    -exec chmod 444 {} \; \
    && find /app -type d -not -path "/app/web/sites/default/files/*" \
    -not -path "/app/private/*" \
    -not -path "/app/config/*" \
    -not -path "/app/vendor/drush/*" \
    -not -path "/app/web/sites/simpletest/*" \
    -exec chmod 555 {} \;

# Ensure sites directory itself is writable for PHP storage
RUN chmod 755 /app/web/sites/default \
    && chown -R application:application /app/web/sites/default

# Keep Drush executable in production
RUN chmod +x /app/vendor/drush/drush/drush \
    && chmod +x /app/vendor/bin/drush 2>/dev/null || true

# Protect settings.php
RUN if [ -f /app/web/sites/default/settings.php ]; then \
    chmod 444 /app/web/sites/default/settings.php; \
    chmod 444 /app/web/sites/default/settings.local.php; \
    fi

# Config directory should be writable for config sync
RUN chown -R application:application /app/config \
    && chmod -R 755 /app/config

# Remove unnecessary files in production
RUN rm -rf /app/.git \
    /app/.gitignore \
    /app/.editorconfig \
    /app/README.md \
    /app/web/INSTALL.txt \
    /app/web/README.md \
    /app/docs/ \
    /app/.idea \
    /app/.devcontainer

EXPOSE 80