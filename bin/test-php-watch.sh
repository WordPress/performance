#!/bin/bash
if ! which inotifywait >/dev/null 2>&1; then
  echo "Error: the inotifywait command is not available. Make sure you have inotify-tools installed."
  exit 1
fi

while true; do
	echo "Waiting for a change in the plugins directory..."
	output=$(inotifywait -e modify,create,delete -r ./plugins 2> /dev/null)
	plugin_slug=$(echo "$output" | awk -F/ '{print $3}')
	sleep 1 # Give the user a chance to copy text from terminal before IDE auto-saves.
	clear
	echo "Running phpunit tests for $(tput bold)$plugin_slug$(tput sgr0):"
	# TODO: Interrupt when a change is made while running tests or re-run if change made since tests started running.
	# Note: This is calling phpunit directly and not the composer script due to extra noise it outputs.
	npm run wp-env --silent -- run tests-cli --env-cwd=/var/www/html/wp-content/plugins/performance -- vendor/bin/phpunit --testsuite "$plugin_slug" "$@"
done
