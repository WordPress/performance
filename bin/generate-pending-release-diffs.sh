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
		svn revert -R "$stable_dir/$plugin_slug" >&2
		svn up --force "$stable_dir/$plugin_slug" >&2
	fi

	remote_stable_tag=$( grep "Stable tag:" "$stable_dir/$plugin_slug/readme.txt" | awk '{print $3}' )
	local_stable_tag=$( grep "Stable tag:" "build/$plugin_slug/readme.txt" | awk '{print $3}' )

	# Copy everything, including generated assets. They are excluded from the *diff* further
	# below rather than from the copy, so that "svn status" reports the true set of files
	# being added, removed, and modified. Excluding them here instead would protect them
	# from --delete and make them invisible to svn entirely: a stray minified asset dropped
	# into an already-tracked directory would then be reported nowhere at all.
	#
	# Compare by checksum (-c) and stamp copied files with a fresh mtime (--no-times).
	# Both are required: "Tested up to: 7.0" -> "7.1" is a same-length edit, and rsync's
	# default quick check (size+mtime) as well as SVN's own stat cache would each conclude
	# the file is unchanged, so the plugin gets wrongly reported as having nothing to release.
	rsync -avzc --no-times --delete --exclude=".svn" "build/$plugin_slug/" "$stable_dir/$plugin_slug/" >&2

	cd "$stable_dir/$plugin_slug/"

	echo "## \`$plugin_slug\`"
	echo

	if [ -z "$( svn status -q )" ]; then
		echo "> [!NOTE]"
		echo "> No changes."
	else
		if [[ "$remote_stable_tag" == "$local_stable_tag" ]]; then
			echo "> [!WARNING]"
			echo "> Stable tag is unchanged at $remote_stable_tag, so no plugin release will occur."
		else
			echo "> [!IMPORTANT]"
			echo "> Stable tag change: $remote_stable_tag → **$local_stable_tag**"
		fi
		echo

		echo "\`svn status\`:"
		echo '```'
		# SVN reports an unversioned directory without listing what is inside it, so a batch
		# of added files would otherwise surface only as their parent directory. Expand those
		# so every file being added is named.
		svn status | while IFS= read -r status_line; do
			echo "$status_line"
			if [ "${status_line:0:1}" = "?" ]; then
				status_path="${status_line:8}"
				if [ -d "$status_path" ]; then
					find "$status_path" -type f | sort | sed 's|^|?       |'
				fi
			fi
		done
		echo '```'
		echo
		echo '<details><summary><code>svn diff</code></summary>'
		echo
		echo '```diff'
		# Keep generated files in the diff so it is clear they changed, but replace their
		# contents with a placeholder. They are minified or bundled build output, so their
		# diffs are unreadable and enormous: the two web-vitals bundles alone were 35% of
		# the entire report, for what amounts to a library version bump. The sibling
		# *.asset.php is left intact, since its 'version' is the readable signal of that.
		svn diff | awk '
			/^Index: / {
				path = substr( $0, 8 )
				suppress = ( path ~ /\.min\.(js|css)$/ || path ~ /(^|\/)build\// )
				if ( path ~ /\.asset\.php$/ ) {
					suppress = 0
				}
				placed = 0
				print
				next
			}
			suppress && /^(=+|--- |\+\+\+ )/ { print; next }
			suppress {
				if ( ! placed ) {
					print "(Built file content suppressed.)"
					placed = 1
				}
				next
			}
			{ print }
		'
		echo '```'
		echo '</details>'
	fi
	echo

	cd - > /dev/null

	echo >&2
done
