ARG PHP_VERSION
# =============================================================================
# Base Stage - Common dependencies and setup
# =============================================================================
FROM webdevops/php-apache:${PHP_VERSION} AS base

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
    libmemcached-dev \
    iputils-ping \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /app

FROM base AS development

RUN mkdir -p /app/web/sites/default/files /app/private

# Copy application files
COPY --chown=application:application composer.json composer.lock /app/
COPY --chown=application:application ./web /app/web
COPY --chown=application:application ./config /app/config

COPY scripts/startup_script.sh /startup_script.sh
RUN chmod +x /startup_script.sh

EXPOSE 80

CMD ["/startup_script.sh"]
