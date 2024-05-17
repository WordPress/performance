=== Optimization Detective (Developer Preview) ===

Contributors:      wordpressdotorg
Requires at least: 6.4
Tested up to:      6.5
Requires PHP:      7.2
Stable tag:        0.2.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Tags:              performance, images

Uses real user metrics to improve heuristics WordPress applies on the frontend to improve image loading priority.

== Description ==

This plugin captures real user metrics about what elements are displayed on the page across a variety of device form factors (e.g. desktop, tablet, and phone) in order to apply loading optimizations which are not possible with WordPress’s current server-side heuristics.

= Background =

WordPress uses [server-side heuristics](https://make.wordpress.org/core/2023/07/13/image-performance-enhancements-in-wordpress-6-3/) to make educated guesses about which images are likely to be in the initial viewport. Likewise, it uses server-side heuristics to identify a hero image which is likely to be the Largest Contentful Paint (LCP) element. To optimize page loading, it avoids lazy-loading any of these images while also adding `fetchpriority=high` to the hero image. When these heuristics are applied successfully, the LCP metric for page loading can be improved 5-10%. Unfortunately, however, there are limitations to the heuristics that make the correct identification of which image is the LCP element only about 50% effective. See [Analyzing the Core Web Vitals performance impact of WordPress 6.3 in the field](https://make.wordpress.org/core/2023/09/19/analyzing-the-core-web-vitals-performance-impact-of-wordpress-6-3-in-the-field/). For example, it is [common](https://github.com/GoogleChromeLabs/wpp-research/pull/73) for the LCP element to vary between different viewport widths, such as desktop versus mobile. Since WordPress's heuristics are completely server-side it has no knowledge of how the page is actually laid out, and it cannot prioritize loading of images according to the client's viewport width.

In order to increase the accuracy of identifying the LCP element, including across various client viewport widths, this plugin gathers metrics from real users (RUM) to detect the actual LCP element and then use this information to optimize the page for future visitors so that the loading of the LCP element is properly prioritized. This is the purpose of Optimization Detective. The approach is heavily inspired by Philip Walton’s [Dynamic LCP Priority: Learning from Past Visits](https://philipwalton.com/articles/dynamic-lcp-priority/). See also the initial exploration document that laid out this project: [Image Loading Optimization via Client-side Detection](https://docs.google.com/document/u/1/d/16qAJ7I_ljhEdx2Cn2VlK7IkiixobY9zNn8FXxN9T9Ls/view).

= Technical Foundation =

At the core of Optimization Detective is the “URL Metric”, information about a page according to how it was loaded by a client with a specific viewport width. This includes which elements were visible in the initial viewport and which one was the LCP element. Each URL on a site can have an associated set of these URL Metrics (stored in a custom post type) which are gathered from real users. It gathers a sample of URL Metrics according to common responsive breakpoints (e.g. mobile, tablet, and desktop). When no more URL Metrics are needed for a URL due to the sample size being obtained for the breakpoints, it discontinues serving the JavaScript to gather the metrics (leveraging the [web-vitals.js](https://github.com/GoogleChrome/web-vitals) library). With the URL Metrics in hand, the output-buffered page is sent through the HTML Tag Processor and the images which were the LCP element for various breakpoints will get prioritized with high-priority preload links (along with `fetchpriority=high` on the actual `img` tag when it is the common LCP element across all breakpoints). LCP elements with background images added via inline `background-image` styles are also prioritized with preload links.

URL Metrics have a “freshness TTL” after which they will be stale and the JavaScript will be served again to start gathering metrics again to ensure that the right elements continue to get their loading prioritized. When a URL Metrics custom post type hasn't been touched in a while, it is automatically garbage-collected.

Prioritizing the loading of images which are the LCP element is only the first optimization implemented as a proof of concept for how other optimizations might also be applied. See a [list of issues](https://github.com/WordPress/performance/labels/%5BPlugin%5D%20Optimization%20Detective) for planned additional optimizations which are only feasible with the URL Metrics RUM data.

Note that by default, URL Metrics are not gathered for administrator users, since they are not normal site visitors, and it is likely that additional elements will be present on the page which are not also shown to non-administrators.

When the `WP_DEBUG` constant is enabled, additional logging for Optimization Detective is added to the browser console.

= Filters =

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
add_filter( 'od_url_metrics_breakpoint_sample_size', function () { return 1; } );
`

**Filter:** `od_url_metric_storage_lock_ttl` (default: 1 minute)

Filters how long a given IP is locked from submitting another metric-storage REST API request. Filtering the TTL to zero will disable any metric storage locking. This is useful, for example, to disable locking when a user is logged-in with code like the following:

`
<?php
add_filter( 'od_metrics_storage_lock_ttl', function ( $ttl ) {
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

Filters the template output buffer prior to sending to the client. This filter is added to implement #43258.

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

= What is the status of this plugin and what does “developer preview” mean? =

This initial release of the Optimization Detective plugin is a preview for the kinds of optimizations that can be applied with this foundation. The intention is that this plugin will serve as an API, planned eventually to be proposed for WordPress core, in which other plugins can extend the functionality to apply additional optimizations. Additional documentation will be made available as development progresses. Follow [progress on GitHub](https://github.com/WordPress/performance/labels/%5BPlugin%5D%20Optimization%20Detective).

= Where can I submit my plugin feedback? =

Feedback is encouraged and much appreciated, especially since this plugin may contain future WordPress core features. If you have suggestions or requests for new features, you can [submit them as an issue in the WordPress Performance Team's GitHub repository](https://github.com/WordPress/performance/issues/new/choose). If you need help with troubleshooting or have a question about the plugin, please [create a new topic on our support forum](https://wordpress.org/support/plugin/optimization-detective/#new-topic-0).

= Where can I report security bugs? =

The Performance team and WordPress community take security bugs seriously. We appreciate your efforts to responsibly disclose your findings, and will make every effort to acknowledge your contributions.

To report a security issue, please visit the [WordPress HackerOne](https://hackerone.com/wordpress) program.

= How can I contribute to the plugin? =

Contributions are always welcome! Learn more about how to get involved in the [Core Performance Team Handbook](https://make.wordpress.org/performance/handbook/get-involved/).

The [plugin source code](https://github.com/WordPress/performance/tree/trunk/plugins/optimization-detective) is located in the [WordPress/performance](https://github.com/WordPress/performance) repo on GitHub.

== Changelog ==

= 0.2.0 =

**Enhancements**

* Add optimization_detective_disabled query var to disable behavior. ([1193](https://github.com/WordPress/performance/pull/1193))
* Facilitate embedding Optimization Detective in other plugins/themes. ([1185](https://github.com/WordPress/performance/pull/1185))
* Use PHP 7.2 features in Optimization Detective. ([1162](https://github.com/WordPress/performance/pull/1162))
* Improve overall code quality with stricter static analysis checks. ([775](https://github.com/WordPress/performance/issues/775))

**Bug Fixes**

* Avoid _doing_it_wrong() for Server-Timing in Optimization Detective when output buffering is not enabled. ([1194](https://github.com/WordPress/performance/pull/1194))
* Ensure only HTML responses are optimized. ([1189](https://github.com/WordPress/performance/pull/1189))
* Fix XPath indices to be 1-based instead of 0-based. ([1191](https://github.com/WordPress/performance/pull/1191))

= 0.1.1 =

* Use plugin slug for generator tag. ([1103](https://github.com/WordPress/performance/pull/1103))
* Prevent detection script injection from breaking import maps in classic themes. ([1084](https://github.com/WordPress/performance/pull/1084))

= 0.1.0 =

* Initial release.
