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

In addition to those locations, run the `npm run since -- -r {version}` command to replace any occurrence of `@since n.e.x.t` with the version number. This ensures any code annotated with the "next" release will now have its proper version number on it. The only exception to this are pre-releases, such as a beta or RC: For those, the stable version number should be used. For example, if the milestone is `1.2.0-beta.2`, the version in e.g. `@since` annotations in the codebase should still be `1.2.0`.

### Update translation strings

The module headers from the plugin have to be translated using a separate `module-i18n.php` file which can be automatically generated and updated. Run `npm run translations` to update the file to reflect the latest available modules.

### Update readme.txt

Run `npm run readme -- -m "{milestoneName}"` to update the readme.txt file with the release changelog, where `{milestoneName}` is the name of the milestone. For example, if the milestone is `1.2.0`, the command needs to be `npm run readme -- -m "1.2.0"`.

After running the command, check the readme.txt file to ensure the new changelog for the release has been added. Also review its changelog entries for whether they make sense and are understandable.

### Open a pull request

Push your local release branch to the GitHub repository and open a pull request against `trunk`. Make sure to tag at least 2 plugin maintainers to request a review. Once the pull request is approved by at least 2 plugin maintainers, the pull request can be merged.

If this is a major or minor release, please keep the release branch around since that branch can be used to later create patch releases from the same state of the codebase.

## Publishing the release

Once the above pull request has been merged, let the other maintainers know on [Slack](https://wordpress.slack.com/archives/performance) that no new commits or pull requests must be added to the branch due to the release process. Then, make sure that all GitHub actions successfully pass for the target branch (e.g. [for `trunk`](https://github.com/WordPress/performance/actions?query=branch%3Atrunk)).

After that, [create a new release tag for the plugin on GitHub](https://github.com/WordPress/performance/releases/new). The release tag should have the same name as the corresponding milestone used earlier, and it should be created from the `trunk` branch (unless this is for a patch release, in which case it should be created from the corresponding `release/...` branch). Finally, add the changelog (it can be found in the readme.txt file) to the release description and create the release.

Once a new version is released on GitHub, the plugin will be deployed to the [WordPress.org repository](https://wordpress.org/plugins/performance-lab/) using [this action](../.github/workflows/deploy-dotorg.yml).

At this point, test the update process from your WordPress site, getting the plugin from wordpress.org. You can then share on [Slack](https://wordpress.slack.com/archives/performance) that the new release has been published and that committing may continue.
