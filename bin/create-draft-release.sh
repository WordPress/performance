#!/bin/bash
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

# Avoid clobbering an existing release/tag; drafts are cheap to delete and recreate.
if gh release view "$tag" &> /dev/null; then
	echo "Error: A release for tag \"$tag\" already exists. Delete it first with:" >&2
	echo "       gh release delete \"$tag\"" >&2
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
npm run prepare-release-notes --silent -- "${notes_args[@]}" > "$notes_file"

if [ ! -s "$notes_file" ]; then
	echo "Error: No release notes were generated; aborting." >&2
	exit 1
fi

echo "Creating draft release \"$tag\" targeting \"$target\"..." >&2
gh release create "$tag" \
	--draft \
	--target "$target" \
	--title "$tag" \
	--notes-file "$notes_file"
