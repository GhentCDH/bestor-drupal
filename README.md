# Bestor Drupal Docker

This repository contains the Drupal site for Bestor and is based on docker to provide the following services

- MySQL
- Elasticsearch
- Memcache
- [Drupal 10.3.6](https://hub.docker.com/_/drupal/tags?name=10.3.6) running PHP on Apache
- Adminer

This repository contains configuration files for the drupal site in `config/sync` directory.
The development entrypoint of the drupal container imports the synced
configurations from this directory at startup. (see [`startup-dev.sh`](./scripts/startup-dev.sh))

This repository contains an SQL dump which is used to initialize the database.
The site then contains a limited amount of example pages.

Note that the images that are used by this example site are not included in the Git repo.
If you need to have them, make sure to put them in the `./initial-content/imported` directory.

## Getting started

First of all, you need an `.env` file.
You can copy the one at `dev.env` to `.env` to get started.

To build and start all services, run

```sh
docker compose up -d --build`
```

If you have the command runner `just` installed, you can simply run:
`just rebuild` every time you want to start out fresh.

The startup script in the drupal container will take about a minute.
To see what it is doing, you can run `docker logs bestor-drupal -f`.
Wait until you see `Running supervisord` or other apache-related log messages.

Then, you can open [localhost:8080](http://localhost:8080) to see the fresh install
of the Bestor drupal site.

To run `drush` open a command line in the container and simply run drush. It is available on PATH in the container.
If you have `just`, you can also do `just drush <command>` (e.g. `just drush cr`).

## Media files

The media files could be copied to the `initial-content` directory.
If the images that the site expects from the mediawiki export are in the `initial-content/imported` directory,
they should then be visible on the site.

## Deployment

@hblomme, correct if wrong: export the drupal config to a set of yaml files in config/ , commit the yaml files and retstore the configuration on the server via drush?

Or via `Manage > Configuration > Development > Configuration synchronization`

See the drupal manual under [Managing your site's configuration](https://www.drupal.org/docs/administering-a-drupal-site/configuration-management/managing-your-sites-configuration)

## Configuration

All of the system configuration is done through environment variables that should be defined in a `.env` file.

An example `dev.env` file is provided with working default values for local development with docker compose.

Just copy it to `.env` and modify the values as needed.

## Credits

Development by [GhentCDH - Ghent University](https://www.ghentcdh.ugent.be/).

Funded by the [GhentCDH research projects](https://www.ghentcdh.ugent.be/projects).
