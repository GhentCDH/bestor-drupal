alias e := enter
alias cr := cache-rebuild

stop:
  docker compose down

start:
  docker compose up -d

enter:
  docker exec -it bestor-drupal bash

migrate arg:
  @echo -e 'Migrating... (\033[92mmigrate:{{arg}}\033[0m)'
  docker exec -it bestor-drupal drush migrate:{{arg}}

ps:
  docker compose ps

drush *arg:
  docker exec -it bestor-drupal drush {{arg}}

cache-rebuild:
  @docker exec bestor-drupal drush cr

rebuild:
    @echo -e 'Rebuilding from scratch...'
    docker compose down -v
    docker compose up -d --build
    docker logs bestor-drupal -f
