#!/bin/bash

# Check for the fswatch command
if ! which fswatch >/dev/null 2>&1; then
	echo "Error: The fswatch command is not available."
	echo "On macOS, you can install it with Homebrew: brew install fswatch"
	exit 1
fi

cd "$(git rev-parse --show-toplevel)"

while true; do
	echo "Waiting for a change in the plugins directory..."

	file=$(fswatch -1 -r \
		--event Created \
		--event Updated \
		--event Removed \
		--include '\.php$' \
		--include '/tests/' \
		./plugins 2>/dev/null | head -n 1)

	# Make the file path relative.
	file="${file#"$PWD/"}"

	plugin_slug=$(echo "$file" | awk -F/ '{print $2}')
	sleep 1 # Give the user a chance to copy text from terminal before IDE auto-saves.
	clear
	echo "Running phpunit tests for $(tput bold)$plugin_slug$(tput sgr0):"
	# TODO: Interrupt when a change is made while running tests or re-run if change made since tests started running.
	# Note: This is calling phpunit directly and not the composer script due to extra noise it outputs.
	npm run wp-env --silent -- run tests-cli --env-cwd=/var/www/html/wp-content/plugins/performance -- vendor/bin/phpunit --testsuite "$plugin_slug" "$@"
done
