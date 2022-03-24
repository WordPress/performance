[Back to overview](https://github.com/WordPress/performance/blob/trunk/docs/README.md)

# GitHub workflow for the Performance Lab plugin

The [Performance team](https://make.wordpress.org/core/2021/10/12/proposal-for-a-performance-team/) uses GitHub to manage all code and related discussions for the Performance Lab plugin. Please follow the workflow below to ensure that all issues are properly tracked.

## Issues
When [opening a new issue](https://github.com/WordPress/performance/issues/new/choose), use the appropriate template: Bug report, Feature request, or Report a security vulnerability. All new issues should include the following labels:

- A _[Focus]_ label, or Infrastructure if the issue relates to the plugin infrastructure. The [Focus] labels are aligned with the Performance team's [focus areas](https://docs.google.com/spreadsheets/d/16N5oZ9wE6AkiqMz7b_707eh24vvpjMwsEG67XFAbxy8/edit#gid=0) and [GitHub Projects](https://github.com/WordPress/performance/projects).
- A _[Type]_ label
- A _[Module]_ label if the issue relates to an existing module

In addition, the new issue should be assigned to an appropriate Project. By default, new issues will automatically be added to the Backlog column within its project. This is intended for tracking any future work that is not currently a high priority as defined by the project’s point(s) of contact (POCs). Contributors are welcome to work on issues in the Backlog, but response rates may be slower than they are on prioritized issues.

## Working on an issue
If you’re interested in working on an issue, it's helpful to  notify the POC for the issue’s focus area by tagging them on the issue and/or notifying them in the [weekly performance chat](https://make.wordpress.org/core/tag/performance/) so that everyone is aware that someone is working on it and effort is not duplicated. For an updated list of POCs, please [see the `CODEOWNERS` file](https://github.com/WordPress/performance/blob/trunk/.github/CODEOWNERS).

When you’re ready to begin work on an issue:
- Assign it to yourself – Depending on your permissions levels, you may not be able to self-assign an issue. Please tag @WordPress/performance-admins on an issue if you need assistance with permissions. Please only assign an issue if you plan to work on it within a reasonable time frame, i.e. within the next 2 weeks. 
- Change the Project column to **In Progress**
- Remove the **Needs Dev** label, if applicable

In addition, there are several **Needs** labels that can be used to clarify next steps on an issue.

### Needs Discussion
Many issues require discussion and definition before development begins. For example, multiple approaches may be considered and the community should discuss them before a contributor proceeds with development. For those issues, add the **Needs Discussion** label and raise the issue in the weekly performance chat.

### Needs Decision
After discussion, a formal vote may be needed to determine how to proceed. If that’s the case, please tag @WordPress/perfromance-admins in the issue for assistance with setting up a vote via GitHub comment. [An example vote can be found here](https://github.com/WordPress/performance/issues/92#issuecomment-1068215411).

### Needs Dev
If an issue requires (more) development and you are unable to complete it yourself, remove yourself as the assignee and add a **Needs Dev** label.

### Needs Testing
If you have completed initial engineering and want community members to test prior to merge, add the Needs Testing label.

## Pull requests
All pull requests must:

- Be associated with an issue
- Have _[Type]_ and _[Focus]_ label matching the related issue’s _[Type]_ and _[Focus]_ labels
- Have either a milestone or the “no milestone” label

When a PR for an issue is ready for review:
- Change the Project column on the issue to **Review**
- Add the **Needs Review** label to the issue

Reviewers will be auto-assigned based upon the issue’s assigned Project. Note that all PRs require at least two reviewers.

## Trac tickets
[Trac](https://core.trac.wordpress.org/) is the system used to track issues related to WordPress core. There is a [‘performance’ label in Trac](https://core.trac.wordpress.org/query?status=!closed&focuses=~performance) that has been added to over 300 issues dating back nearly 15 years. 

While the Performance team’s focus is on the work in the GitHub repo, the community is welcome to consult with the team on performance-related Trac tickets. There may also be tickets in Trac that are duplicates of planned work in the GitHub repo, or warrant further discussion for inclusion in the plugin.

## POC responsibilities
Each [focus area](https://docs.google.com/spreadsheets/d/16N5oZ9wE6AkiqMz7b_707eh24vvpjMwsEG67XFAbxy8/edit#gid=0) has a set one or more assigned points of contact (POCs), as defined in [the `CODEOWNERS` file](https://github.com/WordPress/performance/blob/trunk/.github/CODEOWNERS). POCs are expected to:

- Monitor new issues to ensure that new issues are appropriately designated to their focus area
- Make sure issues in their focus area are also added to the focus area’s GitHub Project
- Monitor and update issues labeled with their focus area
