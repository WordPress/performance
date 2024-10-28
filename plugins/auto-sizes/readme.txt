=== Enhanced Responsive Images ===

Contributors: wordpressdotorg
Tested up to: 6.7
Stable tag:   1.3.0
License:      GPLv2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Tags:         performance, images, auto-sizes

Improvements for responsive images in WordPress.

== Description ==

This plugin implements experimental enhancements for the responsive images functionality in WordPress. Currently, this includes:

1. Improvements to the accuracy of the `sizes` attribute by using available layout information in the theme.
2. Implementation of the new HTML spec for adding `sizes="auto"` to lazy-loaded images. See the HTML spec issue [Add "auto sizes" for lazy-loaded images](https://github.com/whatwg/html/issues/4654).

This plugin integrates with the [Image Prioritizer](https://wordpress.org/plugins/image-prioritizer/) plugin. When that plugin is active, it starts learning about which images are not in the initial viewport based on actual visitors to your site. When it knows which images are below the fold, it then adds `loading=lazy` to these images. This plugin then extends Image Prioritizer to also add `sizes=auto` to these lazy-loaded images.

There are currently **no settings** and no user interface for this plugin since it is designed to work without any configuration.

== Installation ==

= Installation from within WordPress =

1. Visit **Plugins > Add New**.
2. Search for **Enhanced Responsive Images**.
3. Install and activate the **Enhanced Responsive Images** plugin.

= Manual installation =

1. Upload the entire plugin folder to the `/wp-content/plugins/` directory.
2. Visit **Plugins**.
3. Activate the **Enhanced Responsive Images** plugin.

== Frequently Asked Questions ==

= Where can I submit my plugin feedback? =

Feedback is encouraged and much appreciated, especially since this plugin may contain future WordPress core features. If you have suggestions or requests for new features, you can [submit them as an issue in the WordPress Performance Team's GitHub repository](https://github.com/WordPress/performance/issues/new/choose). If you need help with troubleshooting or have a question about the plugin, please [create a new topic on our support forum](https://wordpress.org/support/plugin/auto-sizes/#new-topic-0).

= Where can I report security bugs? =

The Performance team and WordPress community take security bugs seriously. We appreciate your efforts to responsibly disclose your findings, and will make every effort to acknowledge your contributions.

To report a security issue, please visit the [WordPress HackerOne](https://hackerone.com/wordpress) program.

= How can I contribute to the plugin? =

Contributions are always welcome! Learn more about how to get involved in the [Core Performance Team Handbook](https://make.wordpress.org/performance/handbook/get-involved/).

== Changelog ==

= 1.3.0 =

**Enhancements**

* Move Auto Sizes logic from Enhanced Responsive Images to Image Prioritizer. ([1476](https://github.com/WordPress/performance/pull/1476))
* Update auto sizes logic in Enhanced Responsive Images plugin to no longer load if already in Core. ([1547](https://github.com/WordPress/performance/pull/1547))

= 1.2.0 =

**Enhancements**

* Harden logic to add `auto` keyword to `sizes` attribute to prevent duplicate keyword. ([1445](https://github.com/WordPress/performance/pull/1445))
* Use more robust HTML Tag Processor for auto sizes injection. ([1471](https://github.com/WordPress/performance/pull/1471))

**Bug Fixes**

* Remove sizes attribute when responsive images are disabled. ([1399](https://github.com/WordPress/performance/pull/1399))

= 1.1.0 =

**Features**

* Initial implementation of improved image `sizes` algorithm. ([1250](https://github.com/WordPress/performance/pull/1250))

**Enhancements**

* Improved image `sizes` for left/right/center alignment. ([1290](https://github.com/WordPress/performance/pull/1290))
* Integrate Auto Sizes with Image Prioritizer to ensure correct sizes=auto. ([1322](https://github.com/WordPress/performance/pull/1322))
* Update `Auto-sizes for Lazy-loaded Images` plugin name to `Enhanced Responsive Images`. ([1335](https://github.com/WordPress/performance/pull/1335))
* Use correct sizes for small images. ([1252](https://github.com/WordPress/performance/pull/1252))

**Documentation**

* Update the plugin description for Enhanced Responsive Images. ([1339](https://github.com/WordPress/performance/pull/1339))
* Update the plugin header description. ([1344](https://github.com/WordPress/performance/pull/1344))

= 1.0.2 =

* Improve overall code quality with stricter static analysis checks. ([775](https://github.com/WordPress/performance/issues/775))
* Bump minimum PHP requirement to 7.2. ([1130](https://github.com/WordPress/performance/pull/1130))

= 1.0.1 =

* Add auto-sizes generator tag. ([1105](https://github.com/WordPress/performance/pull/1105))
* Bump minimum required WP version to 6.4. ([1062](https://github.com/WordPress/performance/pull/1062))
* Update tested WordPress version to 6.5. ([1027](https://github.com/WordPress/performance/pull/1027))

= 1.0.0 =

* Initial release of the Auto-sizes for Lazy-loaded Images plugin as a standalone plugin. ([904](https://github.com/WordPress/performance/pull/904))
