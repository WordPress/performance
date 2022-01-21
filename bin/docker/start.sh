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
dc up -d
