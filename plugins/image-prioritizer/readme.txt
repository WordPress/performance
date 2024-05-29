=== Image Prioritizer ===

Contributors:      wordpressdotorg
Requires at least: 6.4
Tested up to:      6.5
Requires PHP:      7.2
Stable tag:        0.1.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Tags:              performance, images

Optimizes the loading of the LCP image by leveraging client-side detection with real user metrics.

== Description ==

Optimizes the loading of the LCP image by leveraging client-side detection with real user metrics. Currently, this involves adding `fetchpriority=high` to the LCP image and adding preload links for the LCP `img` as well as any LCP `background-image`.

This plugin requires the [Optimization Detective](https://wordpress.org/plugins/optimization-detective/) plugin as a dependency.

TODO: Flesh out description.

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
