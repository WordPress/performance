# Releasing the performance plugin

This document describes the steps to release the Performance plugin.

## Preparing the release

> The following changes need to be made through a pull request.

### Update the version number

The version number needs to be updated in the following files:

- package.json
- load.php
- readme.txt

### Update readme.txt

Run `npm run readme -- -m "X.Y.Z"` to update the readme.txt file with the release changelog, with X.Y.Z as the release milestone name.

### Create a new GitHub release

Go to https://github.com/WordPress/performance/releases/new to create a new release for the plugin. The release tag should be in the format `X.Y.Z`. Finally, add the changelog (it can be found in the readme.txt file) to the release description and create the release.

## Post-release steps
