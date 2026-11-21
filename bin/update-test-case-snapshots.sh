#!/bin/bash
# Updates snapshots used for testing Optimization Detective
# Move actual.html files on top of an expected.html file in directories that also contain buffer.html files.

set -e

cd "$(git rev-parse --show-toplevel)"

count=0

while IFS= read -r -d '' actual_file; do
	dir_path=$(dirname "$actual_file")
	buffer_file="$dir_path/buffer.html"
	if [ ! -e "$buffer_file" ]; then
		continue
	fi
	expected_file="$dir_path/expected.html"

	count=$((count + 1))

	mv "$actual_file" "$expected_file"
	echo "Updated $expected_file"
done < <(find plugins -name 'actual.html' -path '*/test-cases/*' -print0)

echo "Performed $count snapshot update(s)"
