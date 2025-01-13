=== Optimization Detective ===

Contributors: wordpressdotorg
Tested up to: 6.7
Stable tag:   0.9.0
License:      GPLv2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Tags:         performance, optimization, rum

Provides an API for leveraging real user metrics to detect optimizations to apply on the frontend to improve page performance.

== Description ==

This plugin captures real user metrics about what elements are displayed on the page across a variety of device form factors (e.g. desktop, tablet, and phone) in order to apply loading optimizations which are not possible with WordPress’s current server-side heuristics.

This plugin is a dependency which does not provide end-user functionality on its own. For that, please install the dependent plugin [Image Prioritizer](https://wordpress.org/plugins/image-prioritizer/) or [Embed Optimizer](https://wordpress.org/plugins/embed-optimizer/) (among [others](https://github.com/WordPress/performance/labels/%5BPlugin%5D%20Optimization%20Detective) to come from the WordPress Core Performance team). There are currently **no settings** and no user interface for this plugin since it is designed to work without any configuration.

Your site must have the **REST API accessible** to frontend visitors since this is how metrics are collected about how a page should be optimized.

Please refer to the [full plugin documentation](https://github.com/WordPress/performance/blob/trunk/plugins/optimization-detective/docs/README.md) for a [technical introduction](https://github.com/WordPress/performance/blob/trunk/plugins/optimization-detective/docs/introduction.md), [filter/action hooks](https://github.com/WordPress/performance/blob/trunk/plugins/optimization-detective/docs/hooks.md), and [extensions](https://github.com/WordPress/performance/blob/trunk/plugins/optimization-detective/docs/extensions.md) that show use cases and examples.

== Installation ==

= Installation from the directory within WordPress =

1. Visit **Plugins > Add New** in the WordPress Admin.
2. Search for **Optimization Detective**.
3. Install and activate the **Optimization Detective** plugin.

= Manual installation =

1. Download the plugin [ZIP from WordPress.org](https://downloads.wordpress.org/plugin/optimization-detective.zip) or, after following the [Getting Started instructions](https://make.wordpress.org/performance/handbook/performance-lab/), create a ZIP build from a clone of the [GitHub repo](https://github.com/WordPress/performance) via `npm run build:plugin:optimization-detective --env zip=true`.
2. Visit **Plugins > Add New Plugin** in the WordPress Admin.
3. Click **Upload Plugin**
4. Select the `optimization-detective.zip` file on your system from step 1 and click **Install Now**.
5. Click the **Active Plugin** button.

== Frequently Asked Questions ==

= Where can I submit my plugin feedback? =

Feedback is encouraged and much appreciated, especially since this plugin may contain future WordPress core features. If you have suggestions or requests for new features, you can [submit them as an issue in the WordPress Performance Team's GitHub repository](https://github.com/WordPress/performance/issues/new/choose). If you need help with troubleshooting or have a question about the plugin, please [create a new topic on our support forum](https://wordpress.org/support/plugin/optimization-detective/#new-topic-0).

= Where can I report security bugs? =

The Performance team and WordPress community take security bugs seriously. We appreciate your efforts to responsibly disclose your findings, and will make every effort to acknowledge your contributions.

To report a security issue, please visit the [WordPress HackerOne](https://hackerone.com/wordpress) program.

= How can I contribute to the plugin? =

Contributions are always welcome! Learn more about how to get involved in the [Core Performance Team Handbook](https://make.wordpress.org/performance/handbook/get-involved/).

The [plugin source code](https://github.com/WordPress/performance/tree/trunk/plugins/optimization-detective) is located in the [WordPress/performance](https://github.com/WordPress/performance) repo on GitHub.

== Changelog ==

= 0.9.0 =

**Enhancements**

* Add `fetchpriority=high` to `IMG` when it is the LCP element on desktop and mobile with other viewport groups empty. ([1723](https://github.com/WordPress/performance/pull/1723))
* Improve debugging stored URL Metrics in Optimization Detective. ([1656](https://github.com/WordPress/performance/pull/1656))
* Incorporate page state into ETag computation. ([1722](https://github.com/WordPress/performance/pull/1722))
* Mark existing URL Metrics as stale when a new tag visitor is registered. ([1705](https://github.com/WordPress/performance/pull/1705))
* Set development mode to 'plugin' in the dev environment and allow pages to be optimized when admin is logged-in (when in plugin dev mode). ([1700](https://github.com/WordPress/performance/pull/1700))
* Add `get_xpath_elements_map()` helper methods to `OD_URL_Metric_Group_Collection` and `OD_URL_Metric_Group`, and add `get_all_element_max_intersection_ratios`/`get_element_max_intersection_ratio` methods to `OD_URL_Metric_Group`. ([1654](https://github.com/WordPress/performance/pull/1654))
* Add `get_breadcrumbs()` method to `OD_HTML_Tag_Processor`. ([1707](https://github.com/WordPress/performance/pull/1707))
* Add `get_sample_size()` and `get_freshness_ttl()` methods to `OD_URL_Metric_Group`. ([1697](https://github.com/WordPress/performance/pull/1697))
* Expose `onTTFB`, `onFCP`, `onLCP`, `onINP`, and `onCLS` from web-vitals.js to extension JS modules via args their `initialize` functions. ([1697](https://github.com/WordPress/performance/pull/1697))

**Bug Fixes**

* Prevent submitting URL Metric if viewport size changed. ([1712](https://github.com/WordPress/performance/pull/1712))
* Fix construction of XPath expressions for implicitly closed paragraphs. ([1707](https://github.com/WordPress/performance/pull/1707))

= 0.8.0 =

**Enhancements**

* Serve unminified scripts when `SCRIPT_DEBUG` is enabled. ([1643](https://github.com/WordPress/performance/pull/1643))
* Bump web-vitals from 4.2.3 to 4.2.4. ([1628](https://github.com/WordPress/performance/pull/1628))

**Bug Fixes**

* Eliminate the detection time window which prevented URL Metrics from being gathered when page caching is present. ([1640](https://github.com/WordPress/performance/pull/1640))
* Revise the use of nonces in requests to store a URL Metric and block cross-origin requests. ([1637](https://github.com/WordPress/performance/pull/1637))
* Send post ID of queried object or first post in loop in URL Metric storage request to schedule page cache validation. ([1641](https://github.com/WordPress/performance/pull/1641))
* Fix phpstan errors. ([1627](https://github.com/WordPress/performance/pull/1627))

= 0.7.0 =

**Enhancements**

* Send gathered URL Metric data when the page is hidden/unloaded as opposed to once the page has loaded; this enables the ability to track layout shifts and INP scores over the life of the page. ([1373](https://github.com/WordPress/performance/pull/1373))
* Introduce client-side extensions in the form of script modules which are loaded when the detection logic runs. ([1373](https://github.com/WordPress/performance/pull/1373))
* Add an `od_init` action for extensions to load their code. ([1373](https://github.com/WordPress/performance/pull/1373))
* Introduce `OD_Element` class and improve PHP API. ([1585](https://github.com/WordPress/performance/pull/1585))
* Add group collection helper methods to get the first/last groups. ([1602](https://github.com/WordPress/performance/pull/1602))

**Bug Fixes**

* Fix Optimization Detective compatibility with WooCommerce when Coming Soon page is served. ([1565](https://github.com/WordPress/performance/pull/1565))
* Fix storage of URL Metric when plain non-pretty permalinks are enabled. ([1574](https://github.com/WordPress/performance/pull/1574))

= 0.6.0 =

**Enhancements**

* Allow URL Metric schema to be extended. ([1492](https://github.com/WordPress/performance/pull/1492))
* Clarify docs around a tag visitor's boolean return value. ([1479](https://github.com/WordPress/performance/pull/1479))
* Include UUID with each URL Metric. ([1489](https://github.com/WordPress/performance/pull/1489))
* Introduce get_cursor_move_count() to use instead of get_seek_count() and get_next_token_count(). ([1478](https://github.com/WordPress/performance/pull/1478))

**Bug Fixes**

* Add missing global documentation for `delete_all_posts()`. ([1522](https://github.com/WordPress/performance/pull/1522))
* Introduce viewport aspect ratio validation for URL Metrics. ([1494](https://github.com/WordPress/performance/pull/1494))

= 0.5.0 =

**Enhancements**

* Bump web-vitals from 4.2.1 to 4.2.2. ([1386](https://github.com/WordPress/performance/pull/1386))

**Bug Fixes**

* Disable Optimization Detective by default on the embed template. ([1472](https://github.com/WordPress/performance/pull/1472))
* Ensure only HTML documents are processed by Optimization Detective. ([1442](https://github.com/WordPress/performance/pull/1442))
* Ensure the entire template is passed to the output buffer callback for Optimization Detective to process. ([1317](https://github.com/WordPress/performance/pull/1317))
* Implement full support for intersectionRect/boundingClientRect, fix viewportRect typing, and harden JSON schema. ([1411](https://github.com/WordPress/performance/pull/1411))

= 0.4.1 =

**Enhancements**

* Upgrade web-vitals.js from [v3.5.0](https://github.com/GoogleChrome/web-vitals/blob/main/CHANGELOG.md#v350-2023-09-28) to [v4.2.1](https://github.com/GoogleChrome/web-vitals/blob/main/CHANGELOG.md#v422-2024-07-17).

**Bug Fixes**

* Fix logic for seeking during optimization loop to prevent emitting seek() notices. ([1376](https://github.com/WordPress/performance/pull/1376))

= 0.4.0 =

**Enhancements**

* Avoid passing positional parameters in Optimization Detective. ([1338](https://github.com/WordPress/performance/pull/1338))
* Send preload links via HTTP Link headers in addition to LINK tags. ([1323](https://github.com/WordPress/performance/pull/1323))

= 0.3.1 =

**Enhancements**

* Log URL Metrics group collection to console when debugging is enabled (`WP_DEBUG` is true). ([1295](https://github.com/WordPress/performance/pull/1295))

**Bug Fixes**

* Include non-intersecting elements in URL Metrics to fix lazy-load optimization. ([1293](https://github.com/WordPress/performance/pull/1293))

= 0.3.0 =

* The image optimization features have been split out into a new dependent plugin called [Image Prioritizer](https://wordpress.org/plugins/image-prioritizer/), which also now optimizes image lazy-loading. ([1088](https://github.com/WordPress/performance/issues/1088))

= 0.2.0 =

**Enhancements**

* Add optimization_detective_disabled query var to disable behavior. ([1193](https://github.com/WordPress/performance/pull/1193))
* Facilitate embedding Optimization Detective in other plugins/themes. ([1185](https://github.com/WordPress/performance/pull/1185))
* Use PHP 7.2 features in Optimization Detective. ([1162](https://github.com/WordPress/performance/pull/1162))
* Improve overall code quality with stricter static analysis checks. ([775](https://github.com/WordPress/performance/issues/775))
* Bump minimum PHP requirement to 7.2. ([1130](https://github.com/WordPress/performance/pull/1130))

**Bug Fixes**

* Avoid _doing_it_wrong() for Server-Timing in Optimization Detective when output buffering is not enabled. ([1194](https://github.com/WordPress/performance/pull/1194))
* Ensure only HTML responses are optimized. ([1189](https://github.com/WordPress/performance/pull/1189))
* Fix XPath indices to be 1-based instead of 0-based. ([1191](https://github.com/WordPress/performance/pull/1191))

= 0.1.1 =

* Use plugin slug for generator tag. ([1103](https://github.com/WordPress/performance/pull/1103))
* Prevent detection script injection from breaking import maps in classic themes. ([1084](https://github.com/WordPress/performance/pull/1084))

= 0.1.0 =

* Initial release.

== Upgrade Notice ==

= 0.3.0 =

Image loading optimizations have been moved to a new dependent plugin called Image Prioritizer. The Optimization Detective plugin now serves as a dependency.
