=== WebP Uploads ===

Contributors:      wordpressdotorg
Requires at least: 6.3
Tested up to:      6.3
Requires PHP:      7.0
Stable tag:        1.0.4
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Tags:              performance, images, webp

Creates WebP versions for new JPEG image uploads if supported by the server.

== Description ==

This plugin adds WebP support for media uploads within the WordPress application. WebP images will be generated only for new uploads, pre-existing imagery will not be converted to WebP format. By default, WebP images will only be generated for JPEG uploads, only the original uploaded file will still exist as a JPEG image. All generated image sizes will exist as WebP only. If you wish to change this behaviour, there is a checkbox in `Settings > Media` that - when checked - will alter the behaviour of this plugin to generate both JPEG and WebP images for every sub-size (noting again that this will only affect newly uploaded images, i.e. after making said change).

== Installation ==

= Installation from within WordPress =

1. Visit **Plugins > Add New**.
2. Search for **WebP Uploads**.
3. Install and activate the **WebP Uploads** plugin.

= Manual installation =

1. Upload the entire `webp-uploads` folder to the `/wp-content/plugins/` directory.
2. Visit **Plugins**.
3. Activate the **WebP Uploads** plugin.

== Frequently Asked Questions ==

= Where can I submit my plugin feedback? =

Feedback is encouraged and much appreciated, especially since this plugin may contain future WordPress core features. If you have suggestions or requests for new features, you can [submit them as an issue in the WordPress Performance Team's GitHub repository](https://github.com/WordPress/performance/issues/new/choose). If you need help with troubleshooting or have a question about the plugin, please [create a new topic on our support forum](https://wordpress.org/support/plugin/webp-uploads/#new-topic-0).

= Where can I report security bugs? =

The Performance team and WordPress community take security bugs seriously. We appreciate your efforts to responsibly disclose your findings, and will make every effort to acknowledge your contributions.

To report a security issue, please visit the [WordPress HackerOne](https://hackerone.com/wordpress) program.

= How can I contribute to the plugin? =

Contributions are always welcome! Learn more about how to get involved in the [Core Performance Team Handbook](https://make.wordpress.org/performance/handbook/get-involved/).

= I've activated the WebP Uploads module, but WebP images are not always generated when I upload a JPEG image. Why? =

There are two primary reasons that a WebP image may not be generated:

1. The WebP Uploads plugin has identified that the WebP version of the uploaded JPEG image would have a larger file size than the original JPEG image, so it does not generate the WebP version.
2. The JPEG image was not uploaded to the [Media Library](https://wordpress.com/support/media/). At this time, WebP versions are only generated for images to the Media Library. WebP versions are not generated for JPEG images that are added to your site in other ways, such as in a template file or the [Customizer](https://wordpress.com/support/customizer/).

= With the WebP Uploads plugin activated, will the plugin generate JPEG and WebP versions of every image that I upload? =

By default, the WebP Uploads plugin will only generate WebP versions of the images that you upload. If you wish to have both WebP **and** JPEG versions generated, you can navigate to **Settings > Media** and enable the **Generate JPEG files in addition to WebP** option.

== Changelog ==

= 1.0.4 =

* Bump minimum required PHP version to 7.0 and minimum required WP version to 6.3. ([851](https://github.com/WordPress/performance/pull/851))

= 1.0.3 =

* Add standalone plugin assets. ([815](https://github.com/WordPress/performance/pull/815))

= 1.0.2 =

* Fix WebP handling when editing images based on WordPress 6.3 change. ([796](https://github.com/WordPress/performance/pull/796))

= 1.0.1 =

* Bump tested up to version to 6.3. ([772](https://github.com/WordPress/performance/pull/772))

= 1.0.0 =

* Initial release of the WebP Uploads plugin as a standalone plugin. ([664](https://github.com/WordPress/performance/pull/664))
