#!/bin/sh

# run composer install only if the environment variable is set
if [ -n "$DRUPAL_RUN_COMPOSER" ]; then
    composer install
    # link drush
    ln -s /app/web/vendor/drush/drush/drush /usr/local/bin/drush
fi

# Standard drupal deploy configuration,
# See https://www.drush.org/12.x/deploycommand/

# Ensure files directory exists with proper permissions
mkdir -p /app/web/sites/default/files
chown -R application:application /app/web/sites/default/files
chmod -R 775 /app/web/sites/default/files

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
# sleep 100s # wait for the database to be ready
drush -y updatedb
drush -y cache:rebuild

if [ "$DRUPAL_RUN_CONFIG_IMPORT" = true ]; then
    drush -y config:import
    drush -y cache:rebuild
fi

# run deploy hook
drush deploy:hook