=== Embed Optimizer ===

Contributors: wordpressdotorg
Tested up to: 6.6
Stable tag:   0.2.0
License:      GPLv2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Tags:         performance, embeds

Optimizes the performance of embeds by lazy-loading iframes and scripts.

== Description ==

This plugin's purpose is to optimize the performance of [embeds in WordPress](https://wordpress.org/documentation/article/embeds/), such as YouTube videos, TikToks, and so on. Initially this is achieved by lazy-loading them only when they come into view. This improves performance because embeds are generally very resource-intensive and so lazy-loading them ensures that they don't compete with resources when the page is loading. [Other optimizations](https://github.com/WordPress/performance/issues?q=is%3Aissue+is%3Aopen+label%3A%22%5BPlugin%5D+Embed+Optimizer%22) are planned for the future.

This plugin also recommends that you install and activate the [Optimization Detective](https://wordpress.org/plugins/optimization-detective/) plugin. When it is active, it will start recording which embeds appear in the initial viewport based on actual visitors to your site. With this information in hand, Embed Optimizer will then avoid lazy-loading embeds which appear in the initial viewport (above the fold). This is important because lazy-loading adds a delay which can hurt the user experience and even degrade the Largest Contentful Paint (LCP) score for the page. In addition to not lazy-loading such above-the-fold embeds, Embed Optimizer will add preconnect links for the hosts of network resources known to be required for the most popular embeds (e.g. YouTube, Twitter, Vimeo, Spotify, VideoPress); this can further speed up the loading of critical embeds. Again, these performance enhancements are only enabled when Optimization Detective is active.

There are currently **no settings** and no user interface for this plugin since it is designed to work without any configuration.

== Installation ==

= Installation from within WordPress =

1. Visit **Plugins > Add New**.
2. Search for **Embed Optimizer**.
3. Install and activate the **Embed Optimizer** plugin.

= Manual installation =

1. Upload the entire `embed-optimizer` folder to the `/wp-content/plugins/` directory.
2. Visit **Plugins**.
3. Activate the **Embed Optimizer** plugin.

== Frequently Asked Questions ==

= Where can I submit my plugin feedback? =

Feedback is encouraged and much appreciated, especially since this plugin may contain future WordPress core features. If you have suggestions or requests for new features, you can [submit them as an issue in the WordPress Performance Team's GitHub repository](https://github.com/WordPress/performance/issues/new/choose). If you need help with troubleshooting or have a question about the plugin, please [create a new topic on our support forum](https://wordpress.org/support/plugin/embed-optimizer/#new-topic-0).

= Where can I report security bugs? =

The Performance team and WordPress community take security bugs seriously. We appreciate your efforts to responsibly disclose your findings, and will make every effort to acknowledge your contributions.

To report a security issue, please visit the [WordPress HackerOne](https://hackerone.com/wordpress) program.

= How can I contribute to the plugin? =

Contributions are always welcome! Learn more about how to get involved in the [Core Performance Team Handbook](https://make.wordpress.org/performance/handbook/get-involved/).

The [plugin source code](https://github.com/WordPress/performance/tree/trunk/plugins/embed-optimizer) is located in the [WordPress/performance](https://github.com/WordPress/performance) repo on GitHub.

== Changelog ==

= 0.2.0 =

**Enhancements**

* Facilitate embedding of Embed Optimizer. ([1337](https://github.com/WordPress/performance/pull/1337))
* Leverage Optimization Detective to optimize embeds in Embed Optimizer. ([1302](https://github.com/WordPress/performance/pull/1302))

= 0.1.2 =

**Enhancements**

* Improve overall code quality with stricter static analysis checks. ([775](https://github.com/WordPress/performance/issues/775))
* Bump minimum PHP requirement to 7.2. ([1130](https://github.com/WordPress/performance/pull/1130))

**Bug Fixes**

* Hide post embed iframes with visibility:hidden instead of clipping. ([1192](https://github.com/WordPress/performance/pull/1192))

= 0.1.1 =

* Use plugin slug for generator tag. ([1103](https://github.com/WordPress/performance/pull/1103))
* Bump minimum required WP version to 6.4. ([1076](https://github.com/WordPress/performance/pull/1076))

= 0.1.0 =

* Initial release.
