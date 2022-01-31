# Releasing the performance plugin

This document describes the steps to release the Performance plugin.

## Preparing the release

### Create a local release branch

Before making any changes, create a local branch `release/{milestoneName}` from latest `trunk`, where `{milestoneName}` is the name of the milestone (which should match the release version). For example, if the milestone is `1.2.0`, name the branch `release/1.2.0`.

### Update the version number

The version number needs to be updated in the following files:

- package.json
- load.php
- readme.txt

### Update translation strings

The module headers from the plugin have to be translated using a separate `module-i18n.php` file which can be automatically generated and updated. Run `npm run translations` to update the file to reflect the latest available modules.

### Update readme.txt

Run `npm run readme -- -m "{milestoneName}"` to update the readme.txt file with the release changelog, where `{milestoneName}` is the name of the milestone. For example, if the milestone is `1.2.0`, the command needs to be `npm run readme -- -m "1.2.0"`.

After running the command, check the readme.txt file to ensure the new changelog for the release has been added. Also review its changelog entries for whether they make sense and are understandable.

## Create a new GitHub release

Go to https://github.com/WordPress/performance/releases/new to create a new release for the plugin. The release tag should be in the format `X.Y.Z`. Finally, add the changelog (it can be found in the readme.txt file) to the release description and create the release.

Once a new version is released on GitHub, the plugin will be deployed to the WordPress.org repository using this [action](../.github/workflows/deploy-dotorg.yml).
