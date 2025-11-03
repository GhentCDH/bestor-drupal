#!/bin/sh

# ensure proper permissions for imported media files
chown application:application /app/web/sites/default/files/imported
chmod 775 /app/web/sites/default/files/imported

# run drush
drush -y updatedb # NOTE: in production

# Fix search API if needed
drush search-api:reset-tracker 2>/dev/null || true

# Always import config if the environment variable is set
if [ "$DRUPAL_RUN_CONFIG_IMPORT" = true ]; then
    echo 
    printf "\x1b[32mRunning drush config:import\x1b[0m\n"
    drush -y config:import --debug
    printf "\x1b[32mRunning drush cache:rebuild\x1b[0m\n"
    drush -y cache:rebuild
fi

echo "Running deploy hooks..."
drush deploy:hook

echo "Running supervisord!"
# start the main container command
exec supervisord
