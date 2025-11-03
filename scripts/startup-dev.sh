#!/bin/sh

ln -s /app/initial-content/imported /app/web/sites/default/files/imported
chown application:application /app/web/sites/default/files/imported
chmod 775 /app/web/sites/default/files/imported

# run composer install only if the environment variable is set
if [ -n "$DRUPAL_RUN_COMPOSER" ]; then
    composer install --no-interaction --optimize-autoloader
    # link drush
    ln -s /app/vendor/drush/drush/drush /usr/local/bin/drush
fi

# fix .htaccess permissions
if [ -f /app/web/sites/default/files/.htaccess ]; then
    chown application:application /app/web/sites/default/files/.htaccess
    chmod 644 /app/web/sites/default/files/.htaccess
fi

# run drush

# This can be used to perform the data import locally
if [ "$DRUPAL_FRESH_INSTALL" = true ]; then
    # Site is not installed yet, perform installation procedure
    drush site:install -y \
        --existing-config \
        --db-url="mysql://$DB_USER:$DB_PASSWORD@$DB_HOST:3306/$DB_NAME" \
        --account-name="$DRUPAL_ADMIN_USER" \
        --account-pass="$DRUPAL_ADMIN_PW" \
        --config-dir="/app/config/sync" || {
            echo "Drush site install failed or site already installed, continuing..."
        }

    drush -y updatedb
    drush -y cache:rebuild
    
    # Fix search API after initial install
    echo "Setting up search indexes..."
    drush search-api:reset-tracker 2>/dev/null || true
    drush search-api:rebuild-tracker 2>/dev/null || true
    drush search-api:index
else
    # Site is already installed, just run some updates
    drush -y updatedb -vvv # NOTE: in production

    # Fix search API if needed
    drush search-api:reset-tracker 2>/dev/null || true
fi

# Always import config if the environment variable is set
if [ "$DRUPAL_RUN_CONFIG_IMPORT" = true ]; then
    echo 
    printf "\x1b[32mRunning drush config:import\x1b[0m\n"
    drush -y config:import --debug
    printf "\x1b[32mRunning drush cache:rebuild\x1b[0m\n"
    drush -y cache:rebuild
fi

echo "Running deploy hooks..."
##### drush deploy:hook

printf "\n\nRunning supervisord\n\n\n"
# start the main container command
exec supervisord
