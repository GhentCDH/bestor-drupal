ARG PHP_VERSION
# =============================================================================
# Base Stage - Common dependencies and setup
# =============================================================================
FROM webdevops/php-apache:8.3 AS base

# Set apache document root and index file
ENV WEB_DOCUMENT_ROOT="/app/web"
ENV WEB_DOCUMENT_INDEX="index.php"

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
    gnupg2 \
    libmemcached-dev \
    iputils-ping \
    memcached \
    libmemcached-tools \
    && rm -rf /var/lib/apt/lists/*

# Install node 20.x and npm
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get update \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/* \
    && npm install -g npm@11.6.4

# Set working directory
WORKDIR /app

RUN mkdir -p /app/web/sites/default/files
RUN chown -R application:application /app/web/sites/default/files
RUN chmod -R 775 /app/web/sites/default/files

# =============================================================================
# Development Stage
# =============================================================================

FROM base AS development

COPY scripts/startup-dev.sh /startup.sh
RUN chmod +x /startup.sh

EXPOSE 80

ENV php.opcache.enable=0

CMD ["/startup.sh"]

# =============================================================================
# Production Stage
# =============================================================================

FROM base AS production

# Copy application files
COPY --chown=application:application composer.json composer.lock /app/
COPY --chown=application:application ./web /app/web
COPY --chown=application:application ./config /app/config

RUN composer install --no-interaction --optimize-autoloader --no-dev
RUN ln -s /app/vendor/drush/drush/drush /usr/local/bin/drush

COPY scripts/startup-prod.sh /startup.sh
RUN chmod +x /startup.sh

#set working directory for theme build
WORKDIR /app/web/themes/custom/jakarta
RUN npm install --no-audit
RUN npm run build

WORKDIR /app/

ENV php.opcache.enable=1
ENV php.opcache.memory_consumption=256
ENV php.opcache.interned_strings_buffer=64
ENV php.opcache.max_accelerated_files=50000
ENV php.opcache.max_wasted_percentage=15
ENV php.opcache.save_comments=1
ENV php.opcache.revalidate_freq=2
ENV php.opcache.validate_timestamps=1

EXPOSE 80

CMD ["/startup.sh"]

