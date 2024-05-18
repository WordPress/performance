#!/bin/bash
# Create ZIP files for each built plugin to facilitate manual install in a WordPress site.

set -e

for required_command in git jq zip; do
	if ! command -v "$required_command" &> /dev/null; then
		echo "Error: The $required_command command must be installed to use this script." >&2
		exit 1
	fi
done

cd "$(git rev-parse --show-toplevel)/build"

for plugin_slug in $(jq '.plugins[]' -r ../plugins.json) 'performance-lab'; do
	zip_file="$plugin_slug.zip"
	if [ -e "$zip_file" ]; then
		rm "$zip_file"
	fi
	zip -r "$zip_file" "$plugin_slug"
done
