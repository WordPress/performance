#!/bin/bash
# Generate an overview of the plugin changes between the local filesystem and the latest stable (trunk) versions
# committed to SVN in the WordPress.org Plugin Directory. The output is in Markdown format which is suitable for
# pasting into a GitHub release preparation PR. Program status updates are sent to STDERR, so STDOUT can be piped
# either to the clipboard or to a file for posting to GitHub.
#
# USAGE:
# ./generate-pending-release-diffs.sh > overview.md
# ./generate-pending-release-diffs.sh | pbcopy
# npm run generate-pending-release-diffs --silent

set -e

for required_command in npm git svn jq rsync; do
	if ! command -v "$required_command" &> /dev/null; then
		echo "Error: The $required_command command must be installed to use this script." >&2
		exit 1
	fi
done

cd "$(git rev-parse --show-toplevel)"

npm run build-plugins >&2

stable_dir=/tmp/stable-svn
mkdir -p "$stable_dir"
for plugin_slug in $(jq '.plugins[]' -r plugins.json); do
	echo "# $plugin_slug ###############################" >&2
	if [ ! -d "$stable_dir/$plugin_slug" ]; then
		svn co "https://plugins.svn.wordpress.org/$plugin_slug/trunk/" "$stable_dir/$plugin_slug" >&2
	else
		svn revert "$stable_dir/$plugin_slug" >&2
		svn up "$stable_dir/$plugin_slug" >&2
	fi

	rsync -avz --delete --exclude=".svn" "build/$plugin_slug/" "$stable_dir/$plugin_slug/" >&2

	cd "$stable_dir/$plugin_slug/"

	echo "# \`$plugin_slug\`"
	echo
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
	echo

	cd - > /dev/null

	echo >&2
done
