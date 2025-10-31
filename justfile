alias e := enter
alias cr := cache-rebuild

stop:
  docker compose down

start:
  docker compose up -d

enter:
  docker exec -it bestor-drupal bash

drush *arg:
  docker exec -it bestor-drupal drush {{arg}}

cache-rebuild:
  @docker exec bestor-drupal drush cr

build:
    docker compose up -d --build

rebuild:
    @echo -e 'Rebuilding from scratch...'
    docker compose down -v
    docker compose up -d --build
    docker logs bestor-drupal -f
