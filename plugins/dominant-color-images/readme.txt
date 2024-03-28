=== Image Placeholders ===

Contributors:      wordpressdotorg
Requires at least: 6.4
Tested up to:      6.5
Requires PHP:      7.0
Stable tag:        1.1.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Tags:              performance, images, dominant color

Displays placeholders based on an image's dominant color while the image is loading.

== Description ==

This plugin determines and stores the dominant color for newly uploaded images in the media library within WordPress and then uses it to create a placeholder background of that color in the frontend, visible until the image is loaded.

_This plugin was formerly known as Dominant Color Images._

== Installation ==

= Installation from within WordPress =

1. Visit **Plugins > Add New**.
2. Search for **Image Placeholders**.
3. Install and activate the **Image Placeholders** plugin.

= Manual installation =

1. Upload the entire `dominant-color-images` folder to the `/wp-content/plugins/` directory.
2. Visit **Plugins**.
3. Activate the **Image Placeholders** plugin.

== Frequently Asked Questions ==

= Where can I submit my plugin feedback? =

Feedback is encouraged and much appreciated, especially since this plugin may contain future WordPress core features. If you have suggestions or requests for new features, you can [submit them as an issue in the WordPress Performance Team's GitHub repository](https://github.com/WordPress/performance/issues/new/choose). If you need help with troubleshooting or have a question about the plugin, please [create a new topic on our support forum](https://wordpress.org/support/plugin/dominant-color-images/#new-topic-0).

= Where can I report security bugs? =

The Performance team and WordPress community take security bugs seriously. We appreciate your efforts to responsibly disclose your findings, and will make every effort to acknowledge your contributions.

To report a security issue, please visit the [WordPress HackerOne](https://hackerone.com/wordpress) program.

= How can I contribute to the plugin? =

Contributions are always welcome! Learn more about how to get involved in the [Core Performance Team Handbook](https://make.wordpress.org/performance/handbook/get-involved/).

== Changelog ==

= 1.1.0 =

* Rename plugin to "Image Placeholders". ([1101](https://github.com/WordPress/performance/pull/1101))
* Bump minimum required WP version to 6.4. ([1062](https://github.com/WordPress/performance/pull/1062))
* Update tested WordPress version to 6.5. ([1027](https://github.com/WordPress/performance/pull/1027))

= 1.0.1 =

* Exclude ".wordpress-org" directory when deploying standalone plugins. ([866](https://github.com/WordPress/performance/pull/866))

= 1.0.0 =

* Initial release of the Image Placeholders plugin as a standalone plugin. ([704](https://github.com/WordPress/performance/pull/704))
