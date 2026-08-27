#!/usr/bin/env bash
# Create a draft GitHub release for the current release date using the combined per-plugin
# changelogs (from `npm run prepare-release-notes`) as the release notes.
#
# The tag and release title are the release date, and the target is the release/<date> branch:
#
#   Tag:    $RELEASE_DATE
#   Target: release/$RELEASE_DATE
#   Title:  $RELEASE_DATE
#
# The RELEASE_DATE environment variable is used when set (as established at the start of the
# release handbook via `RELEASE_DATE=$(date +%Y-%m-%d)`); otherwise it defaults to today.
#
# EXAMPLES:
#   RELEASE_DATE=$(date +%Y-%m-%d) ./create-draft-release.sh
#   RELEASE_DATE=2026-06-30 npm run create-draft-release
#   npm run create-draft-release
#
# Any plugin slugs given are forwarded to `npm run prepare-release-notes`, which then reads
# each plugin's changelog straight from its readme.txt rather than looking for a release
# milestone. Use this for a release that does not use milestones:
#
#   RELEASE_DATE=2026-06-30 npm run create-draft-release -- auto-sizes speculation-rules

set -e

for required_command in npm gh git; do
	if ! command -v "$required_command" &> /dev/null; then
		echo "Error: The $required_command command must be installed to use this script." >&2
		exit 1
	fi
done

RELEASE_DATE="${RELEASE_DATE:-$(date +%Y-%m-%d)}"
tag="$RELEASE_DATE"
target="release/$RELEASE_DATE"

cd "$(git rev-parse --show-toplevel)"

# The tag is created from the target branch when the release is published, so the target
# branch must already exist on the remote; fail early with a clear message if it does not.
if ! git ls-remote --exit-code --heads origin "$target" &> /dev/null; then
	echo "Error: The target branch \"$target\" does not exist on the origin remote. Push it first." >&2
	exit 1
fi

# Avoid clobbering an existing release. A draft is cheap to delete and recreate, but a
# published release is immutable, so a new release date must be supplied instead.
if is_draft="$(gh release view "$tag" --json isDraft --jq '.isDraft' 2> /dev/null)"; then
	if [ "$is_draft" = 'true' ]; then
		echo "Error: A draft release for tag \"$tag\" already exists. If it is no longer needed, delete it first with:" >&2
		echo "       gh release delete \"$tag\"" >&2
	else
		echo "Error: A release for tag \"$tag\" has already been published and cannot be overridden. Supply a different RELEASE_DATE." >&2
	fi
	exit 1
fi

notes_file="$(mktemp)"
trap 'rm -f "$notes_file"' EXIT

# Authenticate the milestone lookup with the gh token when available to avoid rate limiting.
notes_args=()
if github_token="$(gh auth token 2> /dev/null)" && [ -n "$github_token" ]; then
	notes_args+=(--token "$github_token")
fi

echo "Generating release notes for $tag..." >&2
npm run prepare-release-notes --silent -- "${notes_args[@]}" "$@" > "$notes_file"

if [ ! -s "$notes_file" ]; then
	echo "Error: No release notes were generated; aborting." >&2
	exit 1
fi

# Guard against notes that cover only some of what is being released. Without plugin slugs
# the selection comes from release milestones, and a plugin missing its milestone is simply
# skipped, which would otherwise yield a release whose notes quietly omit it.
if [ $# -gt 0 ]; then
	for plugin_slug in "$@"; do
		if ! grep -qF "## \`$plugin_slug\`" "$notes_file"; then
			echo "Error: No release notes were generated for \"$plugin_slug\"; aborting." >&2
			exit 1
		fi
	done
else
	echo "Note: No plugin slugs were given, so the release notes cover only plugins with an open, dated release milestone. Check that every plugin being released is present below." >&2
	grep -oE '^## `[^`]+` .*' "$notes_file" | sed 's/^/      /' >&2
fi

echo "Creating draft release \"$tag\" targeting \"$target\"..." >&2
gh release create "$tag" \
	--draft \
	--target "$target" \
	--title "$tag" \
	--notes-file "$notes_file"
