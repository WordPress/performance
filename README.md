# Performance Lab
![Performance Lab plugin banner with icon](https://github.com/WordPress/performance/assets/10103365/99d37ba5-27e3-47ea-8ab8-48de75ee69bf)

Monorepo for the [WordPress Performance Team](https://make.wordpress.org/performance/), primarily for the Performance Lab plugin, which is a collection of standalone performance features.

Details about the Performance Lab plugin, including instructions for getting started and contributing, are available in the [Performance Team Handbook here](https://make.wordpress.org/performance/handbook/performance-lab/).

For WordPress and PHP version requirements, please see the [CONTRIBUTING.md file here](https://github.com/WordPress/performance/blob/trunk/CONTRIBUTING.md).

The feature plugins which are currently featured by this plugin are:

Plugin                          | Slug                      | Experimental | Links
--------------------------------|---------------------------|--------------|-------------
[Image Placeholders][1]         | `dominant-color-images`   | No           | [Source][8],  [Issues][15], [PRs][22]
[Modern Image Formats][2]       | `webp-uploads`            | No           | [Source][9],  [Issues][16], [PRs][23]
[Performant Translations][3]    | `performant-translations` | No           | [Source][10], [Issues][17], [PRs][24]
[Speculative Loading][4]        | `speculation-rules`       | No           | [Source][11], [Issues][18], [PRs][25]
[Embed Optimizer][5]            | `embed-optimizer`         | Yes          | [Source][12], [Issues][19], [PRs][26]
[Enhanced Responsive Images][6] | `auto-sizes`              | Yes          | [Source][13], [Issues][20], [PRs][27]
[Image Prioritizer][7]          | `image-prioritizer`       | Yes          | [Source][14], [Issues][21], [PRs][28]

[1]: https://wordpress.org/plugins/dominant-color-images/
[2]: https://wordpress.org/plugins/webp-uploads/
[3]: https://wordpress.org/plugins/performant-translations/
[4]: https://wordpress.org/plugins/speculation-rules/
[5]: https://wordpress.org/plugins/embed-optimizer/
[6]: https://wordpress.org/plugins/auto-sizes/
[7]: https://wordpress.org/plugins/image-prioritizer/

[8]: https://github.com/WordPress/performance/tree/trunk/plugins/dominant-color-images
[9]: https://github.com/WordPress/performance/tree/trunk/plugins/webp-uploads
[10]: https://github.com/swissspidy/performant-translations
[11]: https://github.com/WordPress/performance/tree/trunk/plugins/speculation-rules
[12]: https://github.com/WordPress/performance/tree/trunk/plugins/embed-optimizer
[13]: https://github.com/WordPress/performance/tree/trunk/plugins/auto-sizes
[14]: https://github.com/WordPress/performance/tree/trunk/plugins/image-prioritizer

[15]: https://github.com/WordPress/performance/issues?q=is%3Aopen+label%3A%22%5BPlugin%5D+Image+Placeholders%22
[16]: https://github.com/WordPress/performance/issues?q=is%3Aopen+label%3A%22%5BPlugin%5D+Modern+Image+Formats%22
[17]: https://github.com/swissspidy/performant-translations/issues
[18]: https://github.com/WordPress/performance/issues?q=is%3Aopen+label%3A%22%5BPlugin%5D+Speculative+Loading%22
[19]: https://github.com/WordPress/performance/issues?q=is%3Aopen+label%3A%22%5BPlugin%5D+Embed+Optimizer%22
[20]: https://github.com/WordPress/performance/issues?q=is%3Aopen+label%3A%22%5BPlugin%5D+Enhanced+Responsive+Images%22
[21]: https://github.com/WordPress/performance/issues?q=is%3Aopen+label%3A%22%5BPlugin%5D+Image+Prioritizer%22

[22]: https://github.com/WordPress/performance/pulls?q=is%3Aopen+label%3A%22%5BPlugin%5D+Image+Placeholders%22
[23]: https://github.com/WordPress/performance/pulls?q=is%3Aopen+label%3A%22%5BPlugin%5D+Modern+Image+Formats%22
[24]: https://github.com/swissspidy/performant-translations/pulls
[25]: https://github.com/WordPress/performance/pulls?q=is%3Aopen+label%3A%22%5BPlugin%5D+Speculative+Loading%22
[26]: https://github.com/WordPress/performance/pulls?q=is%3Aopen+label%3A%22%5BPlugin%5D+Embed+Optimizer%22
[27]: https://github.com/WordPress/performance/pulls?q=is%3Aopen+label%3A%22%5BPlugin%5D+Enhanced+Responsive+Images%22
[28]: https://github.com/WordPress/performance/pulls?q=is%3Aopen+label%3A%22%5BPlugin%5D+Image+Prioritizer%22

Note that the plugin names sometimes diverge from the plugin slugs due to scope changes. For example, a plugin's purpose may change as some of its features are merged into WordPress core.
