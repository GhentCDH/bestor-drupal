#!/bin/sh

# run composer install only if the environment variable is set
if [ -n "$DRUPAL_RUN_COMPOSER" ]; then
    composer install --no-interaction --optimize-autoloader
    # link drush
    ln -s /app/vendor/drush/drush/drush /usr/local/bin/drush
fi

# Standard drupal deploy configuration,
# See https://www.drush.org/12.x/deploycommand/

# Ensure files directory exists with proper permissions
mkdir -p /app/web/sites/default/files
chown -R application:application /app/web/sites/default/files
chmod -R 775 /app/web/sites/default/files

# Copy the imported images from mediawiki to files if they exist
# and if this has not already been done
if [ -d /app/initial-content ] && [ ! -f /app/web/sites/default/private/.initial_content_imported ]; then
    echo "Copying imported images to files directory..."
    cp -r /app/initial-content/imported /app/web/sites/default/files

    # Set proper permissions
    chown -R application:application /app/web/sites/default/files/imported

    touch /app/web/sites/default/private/.initial_content_imported
    echo "Initial content import completed."
fi

# Create .htaccess in files directory if it doesn't exist
if [ ! -f /app/web/sites/default/files/.htaccess ]; then
    cat > /app/web/sites/default/files/.htaccess << 'EOF'
# Turn off all options we don't need.
Options -Indexes -ExecCGI -Includes -MultiViews

# Set the catch-all handler to prevent scripts from being executed.
SetHandler Drupal_Security_Do_Not_Remove_See_SA_2006_006
<Files *>
  SetHandler none
</Files>

# If we know how to do it safely, disable the PHP engine entirely.
<IfModule mod_php.c>
  php_flag engine off
</IfModule>
EOF
    chown application:application /app/web/sites/default/files/.htaccess
    chmod 644 /app/web/sites/default/files/.htaccess
fi

# run drush

INSTALL_LOCK="/app/web/sites/default/private/.drush_site_installed"

if [ -f "$INSTALL_LOCK" ]; then
    # Site is already installed, just run some updates
    
    drush -y updatedb

    # Fix search API if needed
    drush search-api:reset-tracker 2>/dev/null || true
else
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
    
    # Create lock file to prevent reinstall
    touch "$INSTALL_LOCK"
    echo "Install complete, lock file created at $INSTALL_LOCK"
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
drush deploy:hook

printf "\n\nRunning supervisord\n\n\n"
# start the main container command
exec supervisord
