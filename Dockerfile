FROM  drupal:10.4.1-php8.3-apache-bookworm AS drupal-base

# add a basic editor
RUN apt-get update  && apt-get install -y nano micro 

# add a zip tool to expand php composer dependencies
RUN apt-get update  && apt-get install -y zip 

## Install memcache
RUN apt-get update  && apt-get install -y curl
RUN curl -sSLf https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions -o /usr/local/bin/install-php-extensions && \
	chmod +x /usr/local/bin/install-php-extensions && \
	install-php-extensions memcache

## Copy and run the startup script
COPY startup_script.sh /opt/drupal/
RUN chmod +x /opt/drupal/startup_script.sh
CMD ["/opt/drupal/startup_script.sh"]

# target environment
# run composer install
WORKDIR /opt/drupal


##  ---------------------
##  Production
##  ---------------------
FROM drupal-base AS prd

# Copy local Drupal files to the container
COPY web/sites                    /opt/drupal/web/sites
#COPY web/modules                  /opt/drupal/web/modules
#COPY web/themes                   /opt/drupal/web/themes
#COPY web/profiles                 /opt/drupal/web/profiles
COPY config                       /opt/drupal/config
COPY docker_data/drupal/vendor    /opt/drupal/vendor
COPY composer.json                /opt/drupal/
COPY composer.lock                /opt/drupal/

## install composer dependencies, but not dev dependencies
RUN composer install --no-dev

## link drush to the standard path
RUN ln -s /opt/drupal/vendor/drush/drush/drush /usr/local/bin/drush


##  ---------------------
##  Development
##  ---------------------
FROM drupal-base AS dev

#Adds some network diagnostic tools to the dev container
RUN apt-get update  && apt-get install -y iputils-ping telnet
