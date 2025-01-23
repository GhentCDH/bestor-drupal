FROM  drupal:10.4.1-php8.3-apache-bullseye

# Copy local Drupal files to the container
COPY web/sites                    /opt/drupal/web/sites
#COPY web/modules                  /opt/drupal/web/modules
#COPY web/themes                   /opt/drupal/web/themes
#COPY web/profiles                 /opt/drupal/web/profiles
COPY config                       /opt/drupal/config
COPY composer.json                /opt/drupal/composer.json
COPY composer.lock                /opt/drupal/composer.lock
COPY docker_data/drupal/vendor    /opt/drupal/vendor

# run composer install
WORKDIR /opt/drupal
RUN composer install

## Install memcache
RUN apt-get update  && apt-get install -y curl
RUN curl -sSLf https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions -o /usr/local/bin/install-php-extensions && \
	chmod +x /usr/local/bin/install-php-extensions && \
	install-php-extensions memcache

## Copy and run the startup script
COPY startup_script.sh /opt/drupal/startup_script.sh
RUN chmod +x /opt/drupal/startup_script.sh
CMD ["/opt/drupal/startup_script.sh"]

