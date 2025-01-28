#!/bin/sh

if php -m | grep -q 'memcache'; then
    echo "Memcache extension is installed."
else
    echo "Memcache extension is not installed."
    ## install memcache extension
    ( curl -sSLf https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions -o - || echo 'return 1' ) | sh -s  memcache
    sudo /etc/init.d/apache2 reload
fi

cd /opt/drupal

# run composer install
composer install

# link drush
ln -s /opt/drupal/vendor/drush/drush/drush /usr/local/bin/drush

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