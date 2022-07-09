#!/usr/bin/env bash

# Exit if any command fails.
set -e

# Include useful functions.
. "$(dirname "$0")/includes.sh"

# Aggregate profiles.
liveprofui cron:aggregate-all-profiles
