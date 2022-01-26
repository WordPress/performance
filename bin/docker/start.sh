#!/usr/bin/env bash

# Exit if any command fails.
set -e

# Include useful functions.
. "$(dirname "$0")/includes.sh"

# Install dependencies.
if [ ! -e "$ROOT_DIR/vendor/badoo/liveprof/bin/install.php" ]; then
    composer --working-dir="$ROOT_DIR" install
fi

# Start all services.
if [ -z "$(dc ps -q)" ]; then
   dc up -d
fi

# Install WordPress if it is not isntalled yet.
if ! wp core is-installed; then
    # Install WordPress.
    wp core install \
        --title="WordPress Performance Lab" \
        --url="http://localhost:8080" \
        --admin_user="admin" \
        --admin_password="password" \
        --admin_email="test@test.com" \
        --skip-email

    # Configure WordPress
    wp rewrite structure "/%postname%/"

    # Activate required plugins.
    wp plugin activate performance-lab

    # ...
fi

# Install liveprof-ui tables
if ! mysql -e "DESCRIBE aggregator_snapshots" > /dev/null; then
    liveprofui aggregator:install
fi
