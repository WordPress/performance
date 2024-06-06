=== Image Prioritizer ===

Contributors:      wordpressdotorg
Requires at least: 6.4
Tested up to:      6.5
Requires PHP:      7.2
Stable tag:        0.1.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Tags:              performance, optimization, image, lcp, lazy-load

Optimizes LCP image loading with `fetchpriority=high` and applies image lazy-loading by leveraging client-side detection with real user metrics.

== Description ==

This plugin optimizes the loading of images which are the LCP (Largest Contentful Paint) element, including both `img` elements and elements with CSS background images (where there is a `style` attribute with an `background-image` property). Different breakpoints in a theme's responsive design may result in differing elements being the LCP element. Therefore, the LCP element for each breakpoint is captured so that high-fetchpriority preload links with media queries are added which prioritize loading the LCP image specific to the viewport of the visitor.

In addition to prioritizing the loading of the LCP image, this plugin also optimizes image loading by ensuring that `loading=lazy` is omitted from any image that appears in the initial viewport for any of the breakpoints, which by default include:

1. 0-320 (small smartphone)
2. 321-480 (normal smartphone)
3. 481-576 (phablets)
4. >576 (desktop)

If an image does not appear in the initial viewport for any of these viewport groups, then `loading=lazy` is added to the `img` element. 

👉 **Note:** This plugin optimizes pages for actual visitors, and it depends on visitors to optimize pages (since URL metrics need to be collected). As such, you won't see optimizations applied immediately after activating the plugin. And since administrator users are not normal visitors typically, optimizations are not applied for admins by default.

There are currently **no settings** and no user interface for this plugin since it is designed to work without any configuration.

This plugin requires the [Optimization Detective](https://wordpress.org/plugins/optimization-detective/) plugin as a dependency. Please refer to that plugin for additional background on how this plugin works as well as additional developer options. 

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

= 0.1.0 =

* Initial release.
