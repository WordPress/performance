=== Modern Image Formats ===

Contributors: wordpressdotorg
Tested up to: 6.8
Stable tag:   2.6.0
License:      GPLv2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Tags:         performance, images, webp, avif, modern image formats

Converts images to more modern formats such as WebP or AVIF during upload.

== Description ==

This plugin adds WebP and AVIF support for media uploads within the WordPress application. By default, AVIF images will be generated if supported on the hosting server, otherwise WebP will be used as the output format. When both formats are available, the output format can be selected under `Settings > Media`. Modern images will be generated only for new uploads, pre-existing images will only converted to a modern format if images are regenerated. Images can be regenerated with a plugin like [Regenerate Thumbnails](https://wordpress.org/plugins/regenerate-thumbnails/) or via WP-CLI with the `wp media regenerate` [command](https://developer.wordpress.org/cli/commands/media/regenerate/).

By default, only modern image format sub-sizes will be generated for JPEG or PNG uploads - only the original uploaded file will still exist as a JPEG/PNG image, generated image sizes will be WebP or AVIF files. To change this behavior, there is a checkbox in `Settings > Media` "Output fallback images" that - when checked - will result in the plugin generating both the original format as well as WebP or AVIF images for every sub-size (noting again that this will only affect newly uploaded images, i.e. after making said change).

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

= After activation =

1. Visit the **Settings > Media** admin screen.
2. Use the controls in the **Modern Image Formats** section to configure modern image formats.

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

= 2.6.0 =

**Bug Fixes**

* Use modern image formats in background images for Cover blocks and Group blocks. ([2121](https://github.com/WordPress/performance/pull/2121))
* Fixes palette-based PNG uploads failing original full-size AVIF/WebP conversion under GD. ([2024](https://github.com/WordPress/performance/pull/2024))

= 2.5.1 =

**Bug Fixes**

* Fix Modern Image Format not cropping image if crop is an array. ([1887](https://github.com/WordPress/performance/pull/1887))
* Fix incorrect image size selection in `PICTURE` element. ([1885](https://github.com/WordPress/performance/pull/1885))

= 2.5.0 =

**Enhancements**

* Switch to `wp_content_img_tag` filter for improved image handling. ([1772](https://github.com/WordPress/performance/pull/1772))

= 2.4.0 =

**Enhancements**

* Automatically opt into 1536x1536 and 2048x2048 sizes when generating fallback images. ([1679](https://github.com/WordPress/performance/pull/1679))
* Convert WebP to AVIF on upload. ([1724](https://github.com/WordPress/performance/pull/1724))
* Enable end user opt-in to generate all sizes in fallback format. ([1689](https://github.com/WordPress/performance/pull/1689))

= 2.3.0 =

**Enhancements**

* Introduce `webp_uploads_get_file_mime_type` helper function. ([1642](https://github.com/WordPress/performance/pull/1642))
* Rename `webp_uploads_get_file_mime_type` to `webp_uploads_get_attachment_file_mime_type` to clarify scope. ([1662](https://github.com/WordPress/performance/pull/1662))

**Bug Fixes**

* Fix bug that would prevent uploaded images from being converted to the intended output format when having fallback formats enabled. ([1635](https://github.com/WordPress/performance/pull/1635))

= 2.2.0 =

**Enhancements**

* Convert uploaded PNG files to AVIF or WebP. ([1421](https://github.com/WordPress/performance/pull/1421))

**Bug Fixes**

* Account for responsive images being disabled when generating a PICTURE element. ([1449](https://github.com/WordPress/performance/pull/1449))

= 2.1.0 =

**Enhancements**

* Improve disabling checkbox for Picture Element on Media settings screen. ([1470](https://github.com/WordPress/performance/pull/1470))

**Bug Fixes**

* Add missing full size image in PICTURE > SOURCE srcset. ([1437](https://github.com/WordPress/performance/pull/1437))
* Correct the fallback image in PICTURE element. ([1408](https://github.com/WordPress/performance/pull/1408))
* Don't wrap PICTURE element if JPEG fallback is not available. ([1450](https://github.com/WordPress/performance/pull/1450))
* Fix setting sizes attribute on PICTURE > SOURCE elements. ([1354](https://github.com/WordPress/performance/pull/1354))
* Remove string type hint from webp_uploads_sanitize_image_format() to prevent possible fatal error. ([1410](https://github.com/WordPress/performance/pull/1410))

**Documentation**

* Explain how to regenerate images in the Modern Image Formats readme. ([1348](https://github.com/WordPress/performance/pull/1348))

= 2.0.2 =

**Enhancements**

* I18N: Add context to Modern Image Formats section title. ([1287](https://github.com/WordPress/performance/pull/1287))

**Bug Fixes**

* Improve compatibility of styling picture elements. ([1307](https://github.com/WordPress/performance/pull/1307))

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
