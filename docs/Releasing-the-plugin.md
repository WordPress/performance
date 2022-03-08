[Back to overview](./README.md)

# Releasing the performance plugin

This document describes the steps to release the Performance plugin.

## Branching off `trunk`

The cutoff point for a release specifies the point in time where no new features or enhancements should go into the release, only critical fixes. There is no hard rule for when this point should be reached, but the loose guidance is around a week before the tentative release date.

### Create a new release branch

At the cutoff time, create a new remote branch `release/{milestoneName}` from latest `trunk`, where `{milestoneName}` is the name of the milestone (which should match the release version). For example, if the milestone is `1.2.0`, name the branch `release/1.2.0`.

### Communicate the change with other contributors

Once the release branch exists, any pull requests that are relevant for that upcoming release need to be based on the release branch instead of `trunk`. For relevant pull requests that are already in progress at the time of creating the branch, change the base branch accordingly.

Most importantly, immediately communicate with the other plugin contributors that the release branch now exists, e.g. by updating the overall release issue with a new comment or leaving a note in the [#performance Slack channel](https://wordpress.slack.com/archives/performance). This way other contributors should be aware that critical pull requests related to that upcoming release need to be based on the release branch instead of `trunk`.

## Preparing the release

### Create a local release branch

Before making any changes, create a new local branch based on the remote release branch `release/{milestoneName}`. Make the following changes in that local branch.

### Update the version number

The version number needs to be updated in the following files:

- package.json
- package-lock.json
- load.php
- readme.txt

In addition to those locations, run the `npm run since -- -r {version}` command to replace any occurrence of `@since n.e.x.t` with the version number. This ensures any code annotated with the "next" release will now have its proper version number on it. The only exception to this are pre-releases, such as a beta or RC: For those, the stable version number should be used. For example, if the milestone is `1.2.0-beta.2`, the version in e.g. `@since` annotations in the codebase should still be `1.2.0`.

### Update translation strings

The module headers from the plugin have to be translated using a separate `module-i18n.php` file which can be automatically generated and updated. Run `npm run translations` to update the file to reflect the latest available modules.

### Update `readme.txt`

Run `npm run readme -- -m "{milestoneName}"` to update the `readme.txt` file with the release changelog, where `{milestoneName}` is the name of the milestone. For example, if the milestone is `1.2.0`, the command needs to be `npm run readme -- -m "1.2.0"`.

After running the command, check the `readme.txt` file to ensure the new changelog for the release has been added. Also review its changelog entries for whether they make sense and are understandable. Make sure that no unexpected changelog entries are present.

### Open a pull request

Push your local branch to the GitHub repository and open a pull request against the release branch `release/{milestoneName}`. Make sure to tag at least 2 plugin maintainers to request a review, as usual. Once the pull request is approved by at least 2 plugin maintainers, the pull request can be merged.

## Publishing the release

### Notify maintainers and review release branch

Once the above pull request has been merged, let the other maintainers know on [Slack](https://wordpress.slack.com/archives/performance) that no new commits or pull requests must be added to the release branch due to the release process. Then, make sure that all [GitHub workflows](https://github.com/WordPress/performance/actions) successfully pass for the release branch.

### Create the release tag

After that, [create a new release tag for the plugin on GitHub](https://github.com/WordPress/performance/releases/new). The release tag should have the same name as the corresponding milestone used earlier, and it should be created from the release branch `release/{milestoneName}`. Finally, add the changelog (it can be found in the `readme.txt` file) to the release description and create the release.

### Review and test the deployment

Once a new version is released on GitHub, the plugin will be deployed to the [WordPress.org repository](https://wordpress.org/plugins/performance-lab/). [The deployment workflow progress can be tracked here.](https://github.com/WordPress/performance/actions/workflows/deploy-dotorg.yml)

At this point, test the update process from your WordPress site, getting the plugin from wordpress.org. The main aspects to test at this point are:

* Install or update the plugin via wordpress.org, ZIP upload, WP-CLI, etc.
* Test the plugin on different WordPress core versions supported.
* Test the plugin on a multisite environment as well.
* Make sure there are no PHP errors or warnings and that the high-level functionality works.

After successful testing, inform the other maintainers on [Slack](https://wordpress.slack.com/archives/performance) that the new release has been published and that committing may continue.

## Wrapping up the release

### Update `trunk`

Since the release branch was branched off the main branch `trunk`, the latter now needs to be updated to include any additional changes made to the release branch since.

To do that, open a pull request from the release branch to `trunk` (can be done via GitHub UI) and ask two maintainers to approve it. The code in this pull request does not need to be closely reviewed since it was already approved prior, so this is mostly a formal approval.

### Close the release

As a last action, close the [GitHub milestone](https://github.com/WordPress/performance/milestones) for the release. Make sure that all issues in the milestone are closed.

**Don't delete the release branch** since that branch can be used to later create patch releases from the same state of the codebase.
