#!/bin/sh

sleep 20

# Ensure files directory and imported symlink exist with correct ownership and permissions
FILES_DIR="/app/web/sites/default/files"
IMPORTED_IMG_PATH="$FILES_DIR/imported"
IMPORTED_IMG_SOURCE="/app/initial-content/imported"
OWNER="application:application"
DIR_MODE="775"

# Create default files directory if missing; fix permissions/ownership if needed
if [ ! -d "$FILES_DIR" ]; then
    mkdir -p -m "$DIR_MODE" "$FILES_DIR"
    chown "$OWNER" "$FILES_DIR"
else
    current_owner="$(stat -c '%U:%G' "$FILES_DIR" 2>/dev/null || echo '')"
    if [ "$current_owner" != "$OWNER" ]; then
        chown "$OWNER" "$FILES_DIR"
    fi
    current_mode="$(stat -c '%a' "$FILES_DIR" 2>/dev/null || echo '')"
    if [ "$current_mode" != "$DIR_MODE" ]; then
        chmod "$DIR_MODE" "$FILES_DIR"
    fi
fi

# Ensure the imported images symlink exists and points to the correct target
if [ -L "$IMPORTED_IMG_PATH" ]; then
    link_target="$(readlink "$IMPORTED_IMG_PATH" 2>/dev/null || echo '')"
    if [ "$link_target" != "$IMPORTED_IMG_SOURCE" ]; then
        rm -f "$IMPORTED_IMG_PATH"
        ln -s "$IMPORTED_IMG_SOURCE" "$IMPORTED_IMG_PATH"
    fi
elif [ -e "$IMPORTED_IMG_PATH" ]; then
    # Exists but is not a symlink; replace it
    rm -rf "$IMPORTED_IMG_PATH"
    ln -s "$IMPORTED_IMG_SOURCE" "$IMPORTED_IMG_PATH"
else
    ln -s "$IMPORTED_IMG_SOURCE" "$IMPORTED_IMG_PATH"
fi

# Try to ensure the target has correct ownership/permissions (chown/chmod follow symlinks)
chown "$OWNER" "$IMPORTED_IMG_PATH" 2>/dev/null || true
chmod "$DIR_MODE" "$IMPORTED_IMG_PATH" 2>/dev/null || true

# run composer install only if the environment variable is set
if [ -n "$DRUPAL_RUN_COMPOSER" ]; then
    composer install --no-interaction --optimize-autoloader
    # link drush
    ln -s /app/vendor/drush/drush/drush /usr/local/bin/drush
fi

# ensure .htaccess exists in files; copy from default if present and missing
DEFAULT_HTACCESS="/app/web/sites/default/.htaccess"
FILES_HTACCESS="/app/web/sites/default/files/.htaccess"

if [ -f "$DEFAULT_HTACCESS" ] && [ ! -f "$FILES_HTACCESS" ]; then
    cp "$DEFAULT_HTACCESS" "$FILES_HTACCESS"
fi

# fix .htaccess permissions
if [ -f "$FILES_HTACCESS" ]; then
    chown application:application "$FILES_HTACCESS"
    chmod 644 "$FILES_HTACCESS"
fi

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

# Ensure admin user exists and has the correct password, even if environment variables changed
if [ drush user:information "$DRUPAL_ADMIN_USER" > /dev/null 2>&1 ]; then
    printf "\x1b[33mSet admin user password if not already set\x1b[0m\n"
    drush user:password "$DRUPAL_ADMIN_USER" --password="$DRUPAL_ADMIN_PW" 2>/dev/null || true
else
    printf "\x1b[31mAdmin user does not exist, creating superuser and setting password\x1b[0m\n"
    drush user:create "$DRUPAL_ADMIN_USER" --password="$DRUPAL_ADMIN_PW" --mail="$DRUPAL_ADMIN_EMAIL" --roles="administrator"
    drush -y cache:rebuild
fi



echo "Running deploy hooks..."
##### drush deploy:hook

printf "\n\nRunning supervisord\n\n\n"
# start the main container command
exec supervisord

