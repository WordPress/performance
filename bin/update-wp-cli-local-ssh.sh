#!/bin/bash
# This script is intended to be run as the lifecycleScripts.afterStart script.

set -e

cd "$(dirname "$0")/.."

wp_cli_config_file="wp-cli.local.yml"

if [ ! -e "$wp_cli_config_file" ]; then
	echo "path: /var/www/html" >> "$wp_cli_config_file"
fi

hash=$(basename "$(npm run --silent wp-env install-path 2>/dev/null)")
container_id=$(docker ps --format "{{.ID}} {{.Names}}" | grep "$hash-cli" | awk '{print $1}')

grep -v 'ssh:' < "$wp_cli_config_file" > "$wp_cli_config_file-next"

echo "ssh: docker:$container_id" >> "$wp_cli_config_file-next"
mv "$wp_cli_config_file-next" "$wp_cli_config_file"

echo "Container ID for cli: $container_id"
