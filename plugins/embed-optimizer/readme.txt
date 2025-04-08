=== Embed Optimizer ===

Contributors: wordpressdotorg
Tested up to: 6.8
Stable tag:   1.0.0-beta2
License:      GPLv2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Tags:         performance, embeds

Optimizes the performance of embeds through lazy-loading, preconnecting, and reserving space to reduce layout shifts.

== Description ==

This plugin's purpose is to optimize the performance of [embeds in WordPress](https://wordpress.org/documentation/article/embeds/), such as Tweets, YouTube videos, TikToks, and others.

The current optimizations include:

1. Lazy loading embeds just before they come into view.
2. Adding preconnect links for embeds in the initial viewport.
3. Reserving space for embeds that resize to reduce layout shifting.

**Lazy loading embeds** improves performance because embeds are generally very resource-intensive, so lazy loading them ensures that they don't compete with resources when the page is loading. Lazy loading of `IFRAME`\-based embeds is handled simply by adding the `loading=lazy` attribute. Lazy loading embeds that include `SCRIPT` tags is handled by using an Intersection Observer to watch for when the embed’s `FIGURE` container is going to enter the viewport, and then it dynamically inserts the `SCRIPT` tag.

**This plugin also recommends that you install and activate the [Optimization Detective](https://wordpress.org/plugins/optimization-detective/) plugin**, which unlocks several optimizations beyond just lazy loading. Without Optimization Detective, lazy loading can actually degrade performance *when an embed is positioned in the initial viewport*. This is because lazy loading such viewport-initial elements can degrade LCP since rendering is delayed by the logic to determine whether the element is visible. This is why WordPress Core tries its best to [avoid](https://make.wordpress.org/core/2021/07/15/refining-wordpress-cores-lazy-loading-implementation/) [lazy loading](https://make.wordpress.org/core/2021/07/15/refining-wordpress-cores-lazy-loading-implementation/) `IMG` tags which appear in the initial viewport, although the server-side heuristics aren’t perfect. This is where Optimization Detective comes in since it detects whether an embed appears in any breakpoint-specific viewports, like mobile, tablet, and desktop. (See also the [Image Prioritizer](https://wordpress.org/plugins/image-prioritizer/) plugin which extends Optimization Detective to ensure lazy loading is correctly applied based on whether an IMG is in the initial viewport.)

When Optimization Detective is active, it will start keeping track of which embeds appear in the initial viewport based on actual visits to your site. With this information in hand, Embed Optimizer will then avoid lazy loading embeds which appear in the initial viewport. Furthermore, for such above-the-fold embeds Embed Optimizer will also **add preconnect links** for resources known to be used by those embeds. For example, if a YouTube embed appears in the initial viewport, Embed Optimizer with Optimization Detective will omit `loading=lazy` while also adding a preconnect link for `https://i.ytimg.com` which is the domain from which YouTube video poster images are served. Such preconnect links cause the initial-viewport embeds to load even faster.

The other major feature in Embed Optimizer enabled by Optimization Detective is the **reduction of layout shifts** caused by embeds that resize when they load. This is seen commonly in WordPress post embeds or Tweet embeds. Embed Optimizer keeps track of the resized heights of these embeds. With these resized heights stored, Embed Optimizer sets the appropriate height on the container FIGURE element as the viewport-specific `min-height` so that when the embed loads it does not cause a layout shift.

Since Optimization Detective relies on page visits to learn how the page is laid out, you’ll need to wait until you have visits from a mobile and desktop device to start seeing optimizations applied. Also, note that Optimization Detective does not apply optimizations by default for logged-in admin users.

Please note that the optimizations are intended to apply to Embed blocks. So if you do not see optimizations applied, make sure that your embeds are not inside a Classic Block.

Your site must have the **REST API accessible** to unauthenticated frontend visitors since this is how metrics are collected about how a page should be optimized. There are currently **no settings** and no user interface for this plugin since it is designed to work without any configuration.

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

= 1.0.0-beta2 =

**Enhancements**

* Update `OD_HTML_Tag_Processor::next_tag()` to allow `$query` arg and prepare to skip visiting tag closers by default. ([1872](https://github.com/WordPress/performance/pull/1872))
* Expose the logging functions to client-side extensions and automatically account for the value of `isDebug`. ([1895](https://github.com/WordPress/performance/pull/1895))

= 1.0.0-beta1 =

**Enhancements**

* Bump version to 1.0.0-beta1 to indicate graduation from being experimental. See [1846](https://github.com/WordPress/performance/pull/1846).
* Use CSS range syntax in media queries. ([1833](https://github.com/WordPress/performance/pull/1833))

= 0.4.1 =

**Bug Fixes**

* Remove requirement for both mobile and desktop URL metrics to be collected for `preconnect` links to be added. ([1764](https://github.com/WordPress/performance/pull/1764))

= 0.4.0 =

**Enhancements**

* Incorporate media queries into preconnect links to account for whether embeds are in viewport. ([1654](https://github.com/WordPress/performance/pull/1654))
* Serve unminified scripts when `SCRIPT_DEBUG` is enabled. ([1643](https://github.com/WordPress/performance/pull/1643))

= 0.3.0 =

**Enhancements**

* Leverage URL Metrics to reserve space for embeds to reduce CLS. ([1373](https://github.com/WordPress/performance/pull/1373))
* Avoid lazy-loading images and embeds unless there are URL Metrics for both mobile and desktop. ([1604](https://github.com/WordPress/performance/pull/1604))

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
