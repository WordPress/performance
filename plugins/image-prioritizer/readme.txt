=== Image Prioritizer ===

Contributors: wordpressdotorg
Tested up to: 6.8
Stable tag:   1.0.0-beta2
License:      GPLv2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Tags:         performance, optimization, image, lcp, lazy-load

Prioritizes the loading of images and videos based on how they appear to actual visitors: adds fetchpriority, preloads, lazy-loads, and sets sizes.

== Description ==

This plugin optimizes the loading of images (and videos) with prioritization to improve [Largest Contentful Paint](https://web.dev/articles/lcp) (LCP), lazy loading, and more accurate image size selection.

The current optimizations include:

1. Add breakpoint-specific `fetchpriority=high` preload links (both as `LINK[rel=preload]` elements and `Link` response headers) for image URLs of LCP elements:
   1. An `IMG` element, including the `srcset`/`sizes` attributes supplied as `imagesrcset`/`imagesizes` on the `LINK`.
   2. The first `SOURCE` element with a `type` attribute in a `PICTURE` element. (Art-directed `PICTURE` elements using media queries are not supported.)
   3. An element with a CSS `background-image` inline `style` attribute.
   4. An element with a CSS `background-image` applied with a stylesheet (when the image is from an allowed origin).
   5. A `VIDEO` element's `poster` image.
2. Ensure `fetchpriority=high` is only added to an `IMG` when it is the LCP element across all responsive breakpoints.
3. Add `fetchpriority=low` to `IMG` tags which appear in the initial viewport but are not visible, such as when they are subsequent carousel slides.
4. Lazy loading:
   1. Apply lazy loading to `IMG` tags based on whether they appear in any breakpoint’s initial viewport.
   2. Implement lazy loading of CSS background images added via inline `style` attributes.
   3. Lazy-load `VIDEO` tags by setting the appropriate attributes based on whether they appear in the initial viewport. If a `VIDEO` is the LCP element, it gets `preload=auto`; if it is in an initial viewport, the `preload=metadata` default is left; if it is not in an initial viewport, it gets `preload=none`. Lazy-loaded videos also get initial `preload`, `autoplay`, and `poster` attributes restored when the `VIDEO` is going to enter the viewport.
5. Responsive image sizes:
   1. Compute the `sizes` attribute using the widths of an image collected from URL Metrics for each breakpoint (when not lazy-loaded since then handled by `sizes=auto`).
   2. Ensure [`sizes=auto`](https://make.wordpress.org/core/2024/10/18/auto-sizes-for-lazy-loaded-images-in-wordpress-6-7/) is set on `IMG` tags after setting correct lazy-loading (above).
6. Reduce the size of the `poster` image of a `VIDEO` from full size to the size appropriate for the maximum width of the video (on desktop).

**This plugin requires the [Optimization Detective](https://wordpress.org/plugins/optimization-detective/) plugin as a dependency.** Please refer to that plugin for additional background on how this plugin works as well as additional developer options.

👉 **Note:** This plugin optimizes pages for actual visitors, and it depends on visitors to optimize pages. As such, you won't see optimizations applied immediately after activating the plugin. Please wait for URL Metrics to be gathered for both mobile and desktop visits. And since administrator users are not normal visitors typically, optimizations are not applied for admins by default.

Your site must have the **REST API accessible** to unauthenticated frontend visitors since this is how metrics are collected about how a page should be optimized. There are currently **no settings** and no user interface for this plugin since it is designed to work without any configuration.

== Installation ==

= Installation from within WordPress =

1. Visit **Plugins > Add New**.
2. Search for **Image Prioritizer**.
3. Install and activate the **Image Prioritizer** plugin.

= Manual installation =

1. Upload the entire `image-prioritizer` folder to the `/wp-content/plugins/` directory.
2. Visit **Plugins**.
3. Activate the **Image Prioritizer** plugin.

== Frequently Asked Questions ==

= Where can I submit my plugin feedback? =

Feedback is encouraged and much appreciated, especially since this plugin may contain future WordPress core features. If you have suggestions or requests for new features, you can [submit them as an issue in the WordPress Performance Team's GitHub repository](https://github.com/WordPress/performance/issues/new/choose). If you need help with troubleshooting or have a question about the plugin, please [create a new topic on our support forum](https://wordpress.org/support/plugin/image-prioritizer/#new-topic-0).

= Where can I report security bugs? =

The Performance team and WordPress community take security bugs seriously. We appreciate your efforts to responsibly disclose your findings, and will make every effort to acknowledge your contributions.

To report a security issue, please visit the [WordPress HackerOne](https://hackerone.com/wordpress) program.

= How can I contribute to the plugin? =

Contributions are always welcome! Learn more about how to get involved in the [Core Performance Team Handbook](https://make.wordpress.org/performance/handbook/get-involved/).

The [plugin source code](https://github.com/WordPress/performance/tree/trunk/plugins/image-prioritizer) is located in the [WordPress/performance](https://github.com/WordPress/performance) repo on GitHub.

== Changelog ==

= 1.0.0-beta2 =

**Enhancements**

* Update `OD_HTML_Tag_Processor::next_tag()` to allow `$query` arg and prepare to skip visiting tag closers by default. ([1872](https://github.com/WordPress/performance/pull/1872))
* Expose the logging functions to client-side extensions and automatically account for the value of `isDebug`. ([1895](https://github.com/WordPress/performance/pull/1895))

**Bug Fixes**

* Fix URL encoding in Link HTTP response header. ([1907](https://github.com/WordPress/performance/pull/1907))
* Fix unpredictable LCP element being identified in a URL Metric Group. ([1903](https://github.com/WordPress/performance/pull/1903))

= 1.0.0-beta1 =

**Enhancements**

* Bump version to 1.0.0-beta1 to indicate graduation from being experimental. See [1846](https://github.com/WordPress/performance/pull/1846).
* Compute responsive `sizes` attribute based on the `width` from the `boundingClientRect` in captured URL Metrics. ([1840](https://github.com/WordPress/performance/pull/1840))

= 0.3.1 =

**Bug Fixes**

* Remove erroneous check for resource initiator type when considering whether to submit LCP background image. ([1760](https://github.com/WordPress/performance/pull/1760))

= 0.3.0 =

**Enhancements**

* Add preload links LCP picture elements. ([1707](https://github.com/WordPress/performance/pull/1707))
* Harden validation of user-submitted LCP background image URL. ([1713](https://github.com/WordPress/performance/pull/1713))
* Lazy load background images added via inline style attributes. ([1708](https://github.com/WordPress/performance/pull/1708))
* Preload image URLs for LCP elements with external background images. ([1697](https://github.com/WordPress/performance/pull/1697))
* Serve unminified scripts when `SCRIPT_DEBUG` is enabled. ([1643](https://github.com/WordPress/performance/pull/1643))

= 0.2.0 =

**Enhancements**

* Lazy load videos and video posters. ([1596](https://github.com/WordPress/performance/pull/1596))
* Prioritize loading poster image of video LCP elements. ([1498](https://github.com/WordPress/performance/pull/1498))
* Choose smaller video poster image size based on actual dimensions. ([1595](https://github.com/WordPress/performance/pull/1595))
* Add fetchpriority=low to occluded initial-viewport images (e.g. images in hidden carousel slides). ([1482](https://github.com/WordPress/performance/pull/1482))
* Avoid lazy-loading images and embeds unless there are URL Metrics for both mobile and desktop. ([1604](https://github.com/WordPress/performance/pull/1604))

= 0.1.4 =

**Enhancements**

* Move Auto Sizes logic from Enhanced Responsive Images to Image Prioritizer. ([1476](https://github.com/WordPress/performance/pull/1476))

= 0.1.3 =

**Bug Fixes**

* Fix handling of image prioritization when only some viewport groups are populated. ([1404](https://github.com/WordPress/performance/pull/1404))

= 0.1.2 =

* Update PHP logic to account for changes in Optimization Detective API. ([1302](https://github.com/WordPress/performance/pull/1302))

= 0.1.1 =

* Fix background-image styled tag visitor's handling of parsing style without background-image. ([1288](https://github.com/WordPress/performance/pull/1288))

= 0.1.0 =

* Initial release.
