#!/bin/bash
USER_ID=$(id -u)
GROUP_ID=$(id -g)

env UID=$USER_ID GID=$GROUP_ID docker compose --profile tools run --rm php-tools php -d memory_limit=2G ./vendor/bin/phpstan analyse "$@"
