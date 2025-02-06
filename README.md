# Bestor Drupal Docker

This repository contains the Drupal site for Bestor and is based on docker to provide the following services

* MariaDB
* Elasticsearch
* Memcache
* [Drupal 10.3.6](https://hub.docker.com/_/drupal/tags?name=10.3.6) running PHP on Apache

The Drupal site requires some additional configuration and setup. This is done via a [startup script](./startup_script.sh). The script does the following

1) It installs the memcache extension in the docker host if it is not present yet
1) It installs the PHP dependencies via `composer install`
1) It links `drush` to a folder in path so that it can be used everywhere
1) Finally, it starts apache in the foreground

## Getting started

To start all services run

`docker compose up`

Wait for a moment to let the startup script install all dependencies and `open 'http://localhost:8090'`.

To run `drush` open a command line in the container and simply run drush. It is available on PATH in the container.

## Database

The first time the MariaDB service starts it automatically loads the database dump in docker_data/db/initdb. To reset the database to an empty state,
delete all files in docker_data/db/data. On the next start it will automatically reload the database dump.

### Initialize a new drupal database


To remove all databases, delete everything in 
docker/data/db/data and restart the containers. The mediawiki databases should be restored from the database dumps in initdb.

The drupal database is created automatically but is completely empty. To initialize it go to the running drupal instance. To import config I had to do this:

````
drush entity:delete shortcut
drush entity:delete shortcut_set
drush cache-rebuild
drush config-get "system.site" uuid
````

Modify config/sync/system.site.yml to include `uuid: 15a433da-4371-4c9d-86b1-7bbcf299947d`. Finally run the import command wich now should run correctly:

````
drush config:import -y

[notice] Synchronized configuration: create views.view.who_s_new in language.nl.
[notice] Synchronized configuration: create views.view.who_s_online in language.nl.
[notice] Finalizing configuration synchronization.
[success] The configuration was imported successfully.
````

## Deployment

@hblomme, correct if wrong: export the drupal config to a set of yaml files in config/ , commit the yaml files and retstore the configuration on the server via drush?

Or via `Manage > Configuration > Development > Configuration synchronization`

See the drupal manual under [Managing your site's configuration](https://www.drupal.org/docs/administering-a-drupal-site/configuration-management/managing-your-sites-configuration)


## Configuration

The `dev.env` file contains the most relevant configuration settings such as passwords and conneciton info. An example configuration can look like this

````INI
# mariadb config
MARIADB_HOST=bestor-drupal-db
MARIADB_USER=bestor_db_user
MARIADB_PASSWORD=bestor_db_password
MARIADB_DATABASE=drupal_bestor_dev
MARIADB_RANDOM_ROOT_PASSWORD=yes

#drupal config
DRUPAL_SITE_NAME=Bestor Docker DEV

#Run the yaml import script on startup or not
DRUPAL_RUN_CONFIG_IMPORT=true
`````

## Credits

Development by [GhentCDH - Ghent University](https://www.ghentcdh.ugent.be/).

Funded by the [GhentCDH research projects](https://www.ghentcdh.ugent.be/projects).
