=== Fetchpriority ===

Contributors:      wordpressdotorg
Requires at least: 6.1
Tested up to:      6.2
Requires PHP:      5.6
Stable tag:        1.0.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Tags:              performance, images, fetchpriority

Adds a fetchpriority hint for the primary content image on the page to load faster.

== Description ==

This plugin adds the `fetchpriority="high"` attribute to the image that is most likely the LCP image for the current response, improving LCP performance by telling the browser to prioritize that image. The LCP image detection directly relies on the existing WordPress core heuristics that determine whether to not lazy-load an image. The only difference is that, while multiple images may not be lazy-loaded, only a single image will be annotated with `fetchpriority="high"`.

== Installation ==

= Installation from within WordPress =

1. Visit **Plugins > Add New**.
2. Search for **Fetchpriority**.
3. Install and activate the **Fetchpriority** plugin.

= Manual installation =

1. Upload the entire `fetchpriority` folder to the `/wp-content/plugins/` directory.
2. Visit **Plugins**.
3. Activate the **Fetchpriority** plugin.

== Frequently Asked Questions ==

= Where can I submit my plugin feedback? =

Feedback is encouraged and much appreciated, especially since this plugin may contain future WordPress core features. If you have suggestions or requests for new features, you can [submit them as an issue in the WordPress Performance Team's GitHub repository](https://github.com/WordPress/performance/issues/new/choose). If you need help with troubleshooting or have a question about the plugin, please [create a new topic on our support forum](https://wordpress.org/support/plugin/fetchpriority/#new-topic-0).

= Where can I report security bugs? =

The Performance team and WordPress community take security bugs seriously. We appreciate your efforts to responsibly disclose your findings, and will make every effort to acknowledge your contributions.

To report a security issue, please visit the [WordPress HackerOne](https://hackerone.com/wordpress) program.

= How can I contribute to the plugin? =

Contributions are always welcome! Learn more about how to get involved in the [Core Performance Team Handbook](https://make.wordpress.org/performance/handbook/get-involved/).

== Changelog ==

= 1.0.0 =

* Initial release of the Fetchpriority plugin as a standalone plugin. ([704](https://github.com/WordPress/performance/pull/704))
