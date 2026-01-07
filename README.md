# Performance Lab
![Performance Lab plugin banner with icon](https://github.com/WordPress/performance/assets/10103365/99d37ba5-27e3-47ea-8ab8-48de75ee69bf)

[![codecov](https://codecov.io/gh/WordPress/performance/graph/badge.svg?token=zQv52K2LWz)](https://codecov.io/gh/WordPress/performance)

Monorepo for the [WordPress Performance Team](https://make.wordpress.org/performance/), primarily for the Performance Lab plugin, which is a collection of standalone performance features.

Details about the Performance Lab plugin, including instructions for getting started and contributing, are available in the [Performance Team Handbook here](https://make.wordpress.org/performance/handbook/performance-lab/).

For WordPress and PHP version requirements, please see the [CONTRIBUTING.md file here](https://github.com/WordPress/performance/blob/trunk/CONTRIBUTING.md).

The feature plugins which are currently featured by this plugin are:

Plugin                          | Slug                      | Experimental | Links
--------------------------------|---------------------------|--------------|-------------
[Embed Optimizer][5]            | `embed-optimizer`         | No           | [Source][13], [Issues][21], [PRs][29]
[Enhanced Responsive Images][6] | `auto-sizes`              | No           | [Source][14], [Issues][22], [PRs][30]
[Image Placeholders][1]         | `dominant-color-images`   | No           | [Source][9],  [Issues][17], [PRs][25]
[Image Prioritizer][7]          | `image-prioritizer`       | No           | [Source][15], [Issues][23], [PRs][31]
[Instant Back/Forward][41]      | `nocache-bfcache`         | No           | [Source][42], [Issues][43], [PRs][44]
[Modern Image Formats][2]       | `webp-uploads`            | No           | [Source][10], [Issues][18], [PRs][26]
[Optimization Detective][33]    | `optimization-detective`  | No           | [Source][34], [Issues][35], [PRs][36]
[Performant Translations][3]    | `performant-translations` | No           | [Source][11], [Issues][19], [PRs][27]
[Speculative Loading][4]        | `speculation-rules`       | No           | [Source][12], [Issues][20], [PRs][28]
[View Transitions][37]          | `view-transitions`        | Yes          | [Source][38], [Issues][39], [PRs][40]
[Web Worker Offloading][8]      | `web-worker-offloading`   | Yes          | [Source][16], [Issues][24], [PRs][32]

[1]: https://wordpress.org/plugins/dominant-color-images/
[2]: https://wordpress.org/plugins/webp-uploads/
[3]: https://wordpress.org/plugins/performant-translations/
[4]: https://wordpress.org/plugins/speculation-rules/
[5]: https://wordpress.org/plugins/embed-optimizer/
[6]: https://wordpress.org/plugins/auto-sizes/
[7]: https://wordpress.org/plugins/image-prioritizer/
[8]: https://wordpress.org/plugins/web-worker-offloading/
[33]: https://wordpress.org/plugins/optimization-detective/
[37]: https://wordpress.org/plugins/view-transitions/
[41]: https://wordpress.org/plugins/nocache-bfcache/

[9]: https://github.com/WordPress/performance/tree/trunk/plugins/dominant-color-images
[10]: https://github.com/WordPress/performance/tree/trunk/plugins/webp-uploads
[11]: https://github.com/swissspidy/performant-translations
[12]: https://github.com/WordPress/performance/tree/trunk/plugins/speculation-rules
[13]: https://github.com/WordPress/performance/tree/trunk/plugins/embed-optimizer
[14]: https://github.com/WordPress/performance/tree/trunk/plugins/auto-sizes
[15]: https://github.com/WordPress/performance/tree/trunk/plugins/image-prioritizer
[16]: https://github.com/WordPress/performance/tree/trunk/plugins/web-worker-offloading
[34]: https://github.com/WordPress/performance/tree/trunk/plugins/optimization-detective
[38]: https://github.com/WordPress/performance/tree/trunk/plugins/view-transitions
[42]: https://github.com/westonruter/nocache-bfcache

[17]: https://github.com/WordPress/performance/issues?q=is%3Aopen+label%3A%22%5BPlugin%5D+Image+Placeholders%22
[18]: https://github.com/WordPress/performance/issues?q=is%3Aopen+label%3A%22%5BPlugin%5D+Modern+Image+Formats%22
[19]: https://github.com/swissspidy/performant-translations/issues
[20]: https://github.com/WordPress/performance/issues?q=is%3Aopen+label%3A%22%5BPlugin%5D+Speculative+Loading%22
[21]: https://github.com/WordPress/performance/issues?q=is%3Aopen+label%3A%22%5BPlugin%5D+Embed+Optimizer%22
[22]: https://github.com/WordPress/performance/issues?q=is%3Aopen+label%3A%22%5BPlugin%5D+Enhanced+Responsive+Images%22
[23]: https://github.com/WordPress/performance/issues?q=is%3Aopen+label%3A%22%5BPlugin%5D+Image+Prioritizer%22
[24]: https://github.com/WordPress/performance/issues?q=is%3Aopen%20label%3A%22%5BPlugin%5D%20Web%20Worker%20Offloading%22
[35]: https://github.com/WordPress/performance/issues?q=is%3Aopen%20label%3A%22%5BPlugin%5D%20Optimization%20Detective%22
[39]: https://github.com/WordPress/performance/issues?q=is%3Aopen%20label%3A%22%5BPlugin%5D%20View%20Transitions%22
[43]: https://github.com/westonruter/nocache-bfcache/issues

[25]: https://github.com/WordPress/performance/pulls?q=is%3Apr+is%3Aopen+label%3A%22%5BPlugin%5D+Image+Placeholders%22
[26]: https://github.com/WordPress/performance/pulls?q=is%3Apr+is%3Aopen+label%3A%22%5BPlugin%5D+Modern+Image+Formats%22
[27]: https://github.com/swissspidy/performant-translations/pulls
[28]: https://github.com/WordPress/performance/pulls?q=is%3Apr+is%3Aopen+label%3A%22%5BPlugin%5D+Speculative+Loading%22
[29]: https://github.com/WordPress/performance/pulls?q=is%3Apr+is%3Aopen+label%3A%22%5BPlugin%5D+Embed+Optimizer%22
[30]: https://github.com/WordPress/performance/pulls?q=is%3Apr+is%3Aopen+label%3A%22%5BPlugin%5D+Enhanced+Responsive+Images%22
[31]: https://github.com/WordPress/performance/pulls?q=is%3Apr+is%3Aopen+label%3A%22%5BPlugin%5D+Image+Prioritizer%22
[32]: https://github.com/WordPress/performance/pulls?q=is%3Apr+is%3Aopen+label%3A%22%5BPlugin%5D+Web%20Worker%20Offloading%22
[36]: https://github.com/WordPress/performance/pulls?q=is%3Apr+is%3Aopen+label%3A%22%5BPlugin%5D+Optimization%20Detective%22
[40]: https://github.com/WordPress/performance/pulls?q=is%3Apr+is%3Aopen+label%3A%22%5BPlugin%5D+View%20Transitions%22
[44]: https://github.com/westonruter/nocache-bfcache/pulls

Note that the plugin names sometimes diverge from the plugin slugs due to scope changes. For example, a plugin's purpose may change as some of its features are merged into WordPress core.
