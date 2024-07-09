=== Modern Image Formats ===

Contributors: wordpressdotorg
Tested up to: 6.6
Stable tag:   2.0.1
License:      GPLv2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Tags:         performance, images, webp, avif, modern image formats

Converts images to more modern formats such as WebP or AVIF during upload.

== Description ==

This plugin adds WebP and AVIF support for media uploads within the WordPress application. By default, AVIF images will be generated if supported on the hosting server, otherwise WebP will be used as the output format. When both formats are available, the output format can be selected under `Settings > Media`. Modern images will be generated only for new uploads, pre-existing images will only converted to a modern format if images are "Regenerated".

By default, only modern image format sub-sizes will be generated for JPEG uploads - only the original uploaded file will still exist as a JPEG image, generated image sizes use be WebP or AVIF files. To change this behavior, there is a checkbox in `Settings > Media` "Also output JPEG" that - when checked - will result in the plugin generating both JPEG and WebP or AVIF images for every sub-size (noting again that this will only affect newly uploaded images, i.e. after making said change).

_This plugin was formerly known as WebP Uploads._

== Installation ==

= Installation from within WordPress =

1. Visit **Plugins > Add New**.
2. Search for **Modern Image Formats**.
3. Install and activate the **Modern Image Formats** plugin.

= Manual installation =

1. Upload the entire `webp-uploads` folder to the `/wp-content/plugins/` directory.
2. Visit **Plugins**.
3. Activate the **Modern Image Formats** plugin.

== Frequently Asked Questions ==

= Where can I submit my plugin feedback? =

Feedback is encouraged and much appreciated, especially since this plugin may contain future WordPress core features. If you have suggestions or requests for new features, you can [submit them as an issue in the WordPress Performance Team's GitHub repository](https://github.com/WordPress/performance/issues/new/choose). If you need help with troubleshooting or have a question about the plugin, please [create a new topic on our support forum](https://wordpress.org/support/plugin/webp-uploads/#new-topic-0).

= Where can I report security bugs? =

The Performance team and WordPress community take security bugs seriously. We appreciate your efforts to responsibly disclose your findings, and will make every effort to acknowledge your contributions.

To report a security issue, please visit the [WordPress HackerOne](https://hackerone.com/wordpress) program.

= How can I contribute to the plugin? =

Contributions are always welcome! Learn more about how to get involved in the [Core Performance Team Handbook](https://make.wordpress.org/performance/handbook/get-involved/).

= I've activated the Modern Image Formats plugin, but WebP images are not always generated when I upload a JPEG image. Why? =

There are two primary reasons that a WebP image may not be generated:

1. The Modern Image Formats plugin has identified that the WebP version of the uploaded JPEG image would have a larger file size than the original JPEG image, so it does not generate the WebP version.
2. The JPEG image was not uploaded to the [Media Library](https://wordpress.com/support/media/). At this time, WebP versions are only generated for images to the Media Library. WebP versions are not generated for JPEG images that are added to your site in other ways, such as in a template file or the [Customizer](https://wordpress.com/support/customizer/).

= With the Modern Image Formats plugin activated, will the plugin generate JPEG and WebP versions of every image that I upload? =

By default, the Modern Image Formats plugin will only generate WebP versions of the images that you upload. If you wish to have both WebP **and** JPEG versions generated, you can navigate to **Settings > Media** and enable the **Generate JPEG files in addition to WebP** option.

== Changelog ==

= 2.0.1 =

**Bug Fixes**

* Fix fatal error when another the_content filter callback returns null instead of a string. ([1283](https://github.com/WordPress/performance/pull/1283))

= 2.0.0 =

**Features**

* Add `picture` element support. ([73](https://github.com/WordPress/performance/pull/73))
* Add AVIF image format support. Add setting for output image format to choose between WebP and AVIF. ([1176](https://github.com/WordPress/performance/pull/1176))

**Enhancements**

* Improve Settings->Media controls for Modern Image Formats. ([1273](https://github.com/WordPress/performance/pull/1273))
* Remove obsolete fallback script now that picture element is supported. ([1269](https://github.com/WordPress/performance/pull/1269))

= 1.1.1 =

**Enhancements**

* Prepend Settings link in webp-uploads. ([1146](https://github.com/WordPress/performance/pull/1146))
* Improve overall code quality with stricter static analysis checks. ([775](https://github.com/WordPress/performance/issues/775))
* Bump minimum PHP requirement to 7.2. ([1130](https://github.com/WordPress/performance/pull/1130))

**Documentation**

* Updated inline documentation. ([1160](https://github.com/WordPress/performance/pull/1160))

= 1.1.0 =

* Add link to WebP settings to plugins table. ([1036](https://github.com/WordPress/performance/pull/1036))
* Rename plugin to "Modern Image Formats". ([1101](https://github.com/WordPress/performance/pull/1101))
* Use plugin slug for generator tag. ([1103](https://github.com/WordPress/performance/pull/1103))
* Delete option when uninstalling the Modern Image Formats plugin. ([1116](https://github.com/WordPress/performance/pull/1116))
* Bump minimum required WP version to 6.4. ([1062](https://github.com/WordPress/performance/pull/1062))
* Update tested WordPress version to 6.5. ([1027](https://github.com/WordPress/performance/pull/1027))

= 1.0.5 =

* Exclude ".wordpress-org" directory when deploying standalone plugins. ([866](https://github.com/WordPress/performance/pull/866))

= 1.0.4 =

* Bump minimum required PHP version to 7.0 and minimum required WP version to 6.3. ([851](https://github.com/WordPress/performance/pull/851))

= 1.0.3 =

* Add standalone plugin assets. ([815](https://github.com/WordPress/performance/pull/815))

= 1.0.2 =

* Fix WebP handling when editing images based on WordPress 6.3 change. ([796](https://github.com/WordPress/performance/pull/796))

= 1.0.1 =

* Bump tested up to version to 6.3. ([772](https://github.com/WordPress/performance/pull/772))

= 1.0.0 =

* Initial release of the Modern Image Formats plugin as a standalone plugin. ([664](https://github.com/WordPress/performance/pull/664))

== Upgrade Notice ==

= 2.0.0 =

This release adds support for AVIF images and enables selecting the the output image format to choose between WebP and AVIF when both are available. AVIF is used as the default when the server supports it.
