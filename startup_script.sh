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

# run drush dbup
./vendor/drush/drush/drush updb -y
./vendor/drush/drush/drush cr -y

# link drush
ln -s /opt/drupal/vendor/drush/drush/drush /usr/local/bin/drush

# start apache in the foreground
apache2-foreground