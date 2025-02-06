#!/bin/sh

cd /opt/drupal

# run composer install only if the environment variable is set
if [ -n "$DRUPAL_RUN_COMPOSER" ]; then
    composer install
    # link drush
    ln -s /opt/drupal/vendor/drush/drush/drush /usr/local/bin/drush
fi

# Standard drupal deploy configuration,
# See https://www.drush.org/12.x/deploycommand/

# run drush
drush -y updatedb
drush -y cache:rebuild

if [ "$DRUPAL_RUN_CONFIG_IMPORT" = true ]; then
    drush -y config:import
    drush -y cache:rebuild
fi

# run deploy hook
drush deploy:hook

# start apache in the foreground
apache2-foreground