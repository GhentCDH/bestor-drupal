#!/bin/sh

# Ensure files directory and imported symlink exist with correct ownership and permissions
FILES_DIR="/app/web/sites/default/files"
OWNER="application:application"
DIR_MODE="775"

# Create default files directory if missing; fix permissions/ownership if needed
if [ ! -d "$FILES_DIR" ]; then
    mkdir -p -m "$DIR_MODE" "$FILES_DIR"
    chown -R "$OWNER" "$FILES_DIR"
else
    current_owner="$(stat -c '%U:%G' "$FILES_DIR" 2>/dev/null || echo '')"
    if [ "$current_owner" != "$OWNER" ]; then
        chown -R "$OWNER" "$FILES_DIR"
    fi
    current_mode="$(stat -c '%a' "$FILES_DIR" 2>/dev/null || echo '')"
    if [ "$current_mode" != "$DIR_MODE" ]; then
        chmod "$DIR_MODE" "$FILES_DIR"
    fi
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

echo "Setting admin password"
drush user:password admin "$DRUPAL_ADMIN_PW"

echo "Running supervisord!"
# start the main container command
exec supervisord
