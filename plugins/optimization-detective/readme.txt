=== Optimization Detective ===

Contributors: wordpressdotorg
Tested up to: 6.6
Stable tag:   0.4.1
License:      GPLv2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Tags:         performance, optimization, rum

Provides an API for leveraging real user metrics to detect optimizations to apply on the frontend to improve page performance.

== Description ==

This plugin captures real user metrics about what elements are displayed on the page across a variety of device form factors (e.g. desktop, tablet, and phone) in order to apply loading optimizations which are not possible with WordPress’s current server-side heuristics. This plugin is a dependency which does not provide end-user functionality on its own. For that, please install the dependent plugin [Image Prioritizer](https://wordpress.org/plugins/image-prioritizer/) (among [others](https://github.com/WordPress/performance/labels/%5BPlugin%5D%20Optimization%20Detective) to come from the WordPress Core Performance team).

= Background =

WordPress uses [server-side heuristics](https://make.wordpress.org/core/2023/07/13/image-performance-enhancements-in-wordpress-6-3/) to make educated guesses about which images are likely to be in the initial viewport. Likewise, it uses server-side heuristics to identify a hero image which is likely to be the Largest Contentful Paint (LCP) element. To optimize page loading, it avoids lazy-loading any of these images while also adding `fetchpriority=high` to the hero image. When these heuristics are applied successfully, the LCP metric for page loading can be improved 5-10%. Unfortunately, however, there are limitations to the heuristics that make the correct identification of which image is the LCP element only about 50% effective. See [Analyzing the Core Web Vitals performance impact of WordPress 6.3 in the field](https://make.wordpress.org/core/2023/09/19/analyzing-the-core-web-vitals-performance-impact-of-wordpress-6-3-in-the-field/). For example, it is [common](https://github.com/GoogleChromeLabs/wpp-research/pull/73) for the LCP element to vary between different viewport widths, such as desktop versus mobile. Since WordPress's heuristics are completely server-side it has no knowledge of how the page is actually laid out, and it cannot prioritize loading of images according to the client's viewport width.

In order to increase the accuracy of identifying the LCP element, including across various client viewport widths, this plugin gathers metrics from real users (RUM) to detect the actual LCP element and then use this information to optimize the page for future visitors so that the loading of the LCP element is properly prioritized. This is the purpose of Optimization Detective. The approach is heavily inspired by Philip Walton’s [Dynamic LCP Priority: Learning from Past Visits](https://philipwalton.com/articles/dynamic-lcp-priority/). See also the initial exploration document that laid out this project: [Image Loading Optimization via Client-side Detection](https://docs.google.com/document/u/1/d/16qAJ7I_ljhEdx2Cn2VlK7IkiixobY9zNn8FXxN9T9Ls/view).

= Technical Foundation =

At the core of Optimization Detective is the “URL Metric”, information about a page according to how it was loaded by a client with a specific viewport width. This includes which elements were visible in the initial viewport and which one was the LCP element. Each URL on a site can have an associated set of these URL Metrics (stored in a custom post type) which are gathered from real users. It gathers a sample of URL Metrics according to common responsive breakpoints (e.g. mobile, tablet, and desktop). When no more URL Metrics are needed for a URL due to the sample size being obtained for the breakpoints, it discontinues serving the JavaScript to gather the metrics (leveraging the [web-vitals.js](https://github.com/GoogleChrome/web-vitals) library). With the URL Metrics in hand, the output-buffered page is sent through the HTML Tag Processor and--when the [Image Prioritizer](https://wordpress.org/plugins/image-prioritizer/) dependent plugin is installed--the images which were the LCP element for various breakpoints will get prioritized with high-priority preload links (along with `fetchpriority=high` on the actual `img` tag when it is the common LCP element across all breakpoints). LCP elements with background images added via inline `background-image` styles are also prioritized with preload links.

URL Metrics have a “freshness TTL” after which they will be stale and the JavaScript will be served again to start gathering metrics again to ensure that the right elements continue to get their loading prioritized. When a URL Metrics custom post type hasn't been touched in a while, it is automatically garbage-collected.

👉 **Note:** This plugin optimizes pages for actual visitors, and it depends on visitors to optimize pages (since URL metrics need to be collected). As such, you won't see optimizations applied immediately after activating the plugin (and dependent plugin(s)). And since administrator users are not normal visitors typically, optimizations are not applied for admins by default (but this can be overridden with the `od_can_optimize_response` filter below). URL metrics are not collected for administrators because it is likely that additional elements will be present on the page which are not also shown to non-administrators, meaning the URL metrics could not reliably be reused between them. 

There are currently **no settings** and no user interface for this plugin since it is designed to work without any configuration.

When the `WP_DEBUG` constant is enabled, additional logging for Optimization Detective is added to the browser console.

= Hooks =

**Filter:** `od_breakpoint_max_widths` (default: [480, 600, 782])

Filters the breakpoint max widths to group URL metrics for various viewports. Each number represents the maximum width (inclusive) for a given breakpoint. So if there is one number, 480, then this means there will be two viewport groupings, one for 0<=480, and another >480. If instead there were three provided breakpoints (320, 480, 576) then this means there will be four groups:

 1. 0-320 (small smartphone)
 2. 321-480 (normal smartphone)
 3. 481-576 (phablets)
 4. >576 (desktop)

The default breakpoints are reused from Gutenberg which appear to be used the most in media queries that affect frontend styles.

**Filter:** `od_can_optimize_response` (default: boolean condition, see below)

Filters whether the current response can be optimized. By default, detection and optimization are only performed when:

1. It’s not a search template (i.e. `is_search()`).
2. It’s not the Customizer preview.
3. It’s not the response to a `POST` request.
4. The user is not an administrator (i.e. the `customize` capability).

During development, you may want to force this to always be enabled:

`
<?php
add_filter( 'od_can_optimize_response', '__return_true' );
`

**Filter:** `od_url_metrics_breakpoint_sample_size` (default: 3)

Filters the sample size for a breakpoint's URL metrics on a given URL. The sample size must be greater than zero. During development, it may be helpful to reduce the sample size to 1:

`
<?php
add_filter( 'od_url_metrics_breakpoint_sample_size', function (): int {
	return 1;
} );
`

**Filter:** `od_url_metric_storage_lock_ttl` (default: 1 minute)

Filters how long a given IP is locked from submitting another metric-storage REST API request. Filtering the TTL to zero will disable any metric storage locking. This is useful, for example, to disable locking when a user is logged-in with code like the following:

`
<?php
add_filter( 'od_metrics_storage_lock_ttl', function ( int $ttl ): int {
    return is_user_logged_in() ? 0 : $ttl;
} );
`

**Filter:** `od_url_metric_freshness_ttl` (default: 1 day)

Filters the freshness age (TTL) for a given URL metric. The freshness TTL must be at least zero, in which it considers URL metrics to always be stale. In practice, the value should be at least an hour. During development, this can be useful to set to zero:

`
<?php
add_filter( 'od_url_metric_freshness_ttl', '__return_zero' );
`

**Filter:** `od_detection_time_window` (default: 5 seconds)

Filters the time window between serve time and run time in which loading detection is allowed to run. This amount is the allowance between when the page was first generated (and perhaps cached) and when the detect function on the page is allowed to perform its detection logic and submit the request to store the results. This avoids situations in which there are missing URL Metrics in which case a site with page caching which also has a lot of traffic could result in a cache stampede.

**Filter:** `od_template_output_buffer` (default: the HTML response)

Filters the template output buffer prior to sending to the client. This filter is added to implement [#43258](https://core.trac.wordpress.org/ticket/43258) in WordPress core.

== Installation ==

= Installation from within WordPress =

1. Visit **Plugins > Add New**.
2. Search for **Optimization Detective**.
3. Install and activate the **Optimization Detective** plugin.

= Manual installation =

1. Upload the entire `optimization-detective` folder to the `/wp-content/plugins/` directory.
2. Visit **Plugins**.
3. Activate the **Optimization Detective** plugin.

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

* Log URL metrics group collection to console when debugging is enabled (`WP_DEBUG` is true). ([1295](https://github.com/WordPress/performance/pull/1295))

**Bug Fixes**

* Include non-intersecting elements in URL metrics to fix lazy-load optimization. ([1293](https://github.com/WordPress/performance/pull/1293))

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
