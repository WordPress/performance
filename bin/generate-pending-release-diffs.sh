#!/bin/bash
# Generate an overview of the plugin changes between the local filesystem and the latest stable (trunk) versions
# committed to SVN in the WordPress.org Plugin Directory. The output is in Markdown format which is suitable for
# pasting into a GitHub release preparation PR. Program status updates are sent to STDERR, so STDOUT can be piped
# either to the clipboard or to a file for posting to GitHub.
#
# EXAMPLES:
# ./generate-pending-release-diffs.sh > all-plugins.md
# ./generate-pending-release-diffs.sh | pbcopy
# ./generate-pending-release-diffs.sh optimization-detective image-prioritizer > two-plugins.md
# npm run generate-pending-release-diffs --silent
# npm run generate-pending-release-diffs --silent auto-sizes
# npm run generate-pending-release-diffs --silent auto-sizes webp-uploads

set -e

for required_command in npm git svn jq rsync; do
	if ! command -v "$required_command" &> /dev/null; then
		echo "Error: The $required_command command must be installed to use this script." >&2
		exit 1
	fi
done

cd "$(git rev-parse --show-toplevel)"

stable_dir=/tmp/stable-svn
mkdir -p "$stable_dir"
for plugin_slug in $( if [ $# -gt 0 ]; then echo "$@"; else jq '.plugins[]' -r plugins.json; fi ); do
	echo "# $plugin_slug ###############################" >&2
	if ! npm run "build:plugin:$plugin_slug" >&2; then
		echo "Failed to build plugin: $plugin_slug" >&2
		exit 1
	fi

	if [ ! -d "$stable_dir/$plugin_slug" ]; then
		svn co "https://plugins.svn.wordpress.org/$plugin_slug/trunk/" "$stable_dir/$plugin_slug" >&2
	else
		svn revert "$stable_dir/$plugin_slug" >&2
		svn up --force "$stable_dir/$plugin_slug" >&2
	fi

	rsync -avz --delete --exclude=".svn" "build/$plugin_slug/" "$stable_dir/$plugin_slug/" >&2

	cd "$stable_dir/$plugin_slug/"

	echo "## \`$plugin_slug\`"
	echo

	if [ -z "$( svn status -q )" ]; then
		echo "No changes."
	else
		echo "\`svn status\`:"
		echo '```'
		svn status
		echo '```'
		echo
		echo '<details><summary><code>svn diff</code></summary>'
		echo
		echo '```diff'
		svn diff
		echo '```'
		echo '</details>'
	fi
	echo

	cd - > /dev/null

	echo >&2
done
