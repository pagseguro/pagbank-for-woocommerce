#!/bin/bash
USER_ID=$(id -u)
GROUP_ID=$(id -g)

env UID=$USER_ID GID=$GROUP_ID docker compose --profile tools run --rm php-tools ./vendor/bin/phpunit "$@"
