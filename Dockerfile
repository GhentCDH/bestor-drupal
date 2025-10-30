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
    iputils-ping \
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

RUN mkdir -p /app/web/sites/default/files \
    /app/private

# Copy application files
COPY --chown=application:application composer.json composer.lock /app/
COPY --chown=application:application ./web /app/web
COPY --chown=application:application ./config /app/config

COPY scripts/startup_script.sh /startup_script.sh
RUN chmod +x /startup_script.sh

EXPOSE 80

CMD ["/startup_script.sh"]

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
COPY --chown=application:application composer.json composer.lock /app/

# Install Composer dependencies WITHOUT dev packages
RUN composer install --no-interaction --optimize-autoloader --no-dev \
    && composer clear-cache \
    && ln -s /app/vendor/drush/drush/drush /usr/local/bin/drush

COPY --chown=application:application ./web /app/web
COPY --chown=application:application ./config /app/config

# Create necessary directories (including PHP storage and temp)
RUN mkdir -p /app/web/sites/default/files \
    /app/web/sites/default/files/php \
    /app/web/sites/default/files/css \
    /app/web/sites/default/files/js \
    /app/config \
    /app/private \
    /tmp

EXPOSE 80
