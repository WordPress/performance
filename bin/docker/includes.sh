#!/usr/bin/env bash

DOCKER_DIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )
BIN_DIR=$( dirname "$DOCKER_DIR" )
ROOT_DIR=$( dirname "$BIN_DIR" )

# TTY compatibility.
# For some environments, a TTY may not be available (e.g. GitHub Actions).
# Docker Compose allocates a TTY by default, so it's important that we disable it
# automatically when needed.
if [ -t 0 ]; then
	COMPOSE_EXEC_ARGS=""
else
	COMPOSE_EXEC_ARGS="-T" # Disable pseudo-tty allocation. By default `docker-compose exec` allocates a TTY.
fi

##
# Calls docker-compose with common options.
##
dc() {
	docker-compose \
        --project-name="performance-lab" \
        --project-directory="$DOCKER_DIR" \
        "$@"
}

##
# Executes a WP CLI request in the wordpress container.
##
wp() {
	dc exec $COMPOSE_EXEC_ARGS wordpress wp --allow-root "$@"
}
