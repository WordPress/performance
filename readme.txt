=== Performance Lab ===

Contributors:      wordpressdotorg
Requires at least: 5.8
Tested up to:      6.0
Requires PHP:      5.6
Stable tag:        1.3.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Tags:              performance, images, javascript, site health, measurement, object caching

Performance plugin from the WordPress Performance Group, which is a collection of standalone performance modules.

== Description ==

The Performance Lab plugin is a collection of modules focused on enhancing performance of your site, most of which should eventually be merged into WordPress core. The plugin allows to individually enable and test the modules to get their benefits before they become available in WordPress core, and to provide feedback to further improve the solutions.

Currently the plugin includes the following performance modules:

* **Dominant Color:** Adds support to store dominant color for an image and create a placeholder background with that color.
* **WebP Uploads:** Creates WebP versions for new JPEG image uploads if supported by the server.
* **Audit Full Page Cache:** Adds a check for full page cache in Site Health status.
* **WebP Support:** Adds a WebP support check in Site Health status.
* **Audit Autoloaded Options:** Adds a check for autoloaded options in Site Health status.
* **Audit Enqueued Assets:** Adds a CSS and JS resource check in Site Health status.
* **Persistent Object Cache Health Check:** Adds a persistent object cache check for sites with non-trivial amounts of data in Site Health status.

== Installation ==

= Installation from within WordPress =

1. Visit **Plugins > Add New**.
2. Search for **Performance Lab**.
3. Install and activate the Performance Lab plugin.

= Manual installation =

1. Upload the entire `performance-lab` folder to the `/wp-content/plugins/` directory.
2. Visit **Plugins**.
3. Activate the Performance Lab plugin.

= After activation =

1. Visit the new **Settings > Performance** menu.
2. Enable the individual modules you would like to use.

== Frequently Asked Questions ==

= What is the purpose of this plugin? =

The primary purpose of the Performance Lab plugin is to allow testing of various performance modules for which the goal is to eventually land in WordPress core. It is essentially a collection of "feature plugins", which makes it different from other performance plugins that offer performance features which are not targeted at WordPress core and potentially rely on functionality that would not be feasible to use in WordPress core. The list of available modules will regularly change: Existing modules may be removed after they have been released in WordPress core, while new modules may be added in any release.

= Can I use this plugin on my production site? =

Per the primary purpose of the plugin (see above), it can mostly be considered a beta testing plugin for the various performance modules it includes. However, unless a module is explicitly marked as "experimental", it has been tested and established to a degree where it should be okay to use in production. Still, as with every plugin, you are doing so at your own risk.

= Where can I submit my plugin feedback? =

Especially since this is a collection of WordPress core feature plugins, providing feedback is encouraged and much appreciated! You can submit your feedback either in the [plugin support forum](https://wordpress.org/support/plugin/performance-lab/) or, if you have a specific issue to report, in its [GitHub repository](https://github.com/WordPress/performance).

= How can I contribute to the plugin? =

Contributions welcome! There are several ways to contribute:

* Raise an issue or submit a pull request in the [Github repository for the plugin](https://github.com/WordPress/performance)
* Translate the plugin into your language at [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/performance-lab)
* Join the weekly chat (Tuesdays at 16:00 UTC) in the [#performance channel on Slack](https://wordpress.slack.com/archives/performance)

== Changelog ==

= 1.3.0 =

**Enhancements**

* Images: Add replacing of images only in frontend context. ([424](https://github.com/WordPress/performance/pull/424))
* Images: Allow control for which image sizes to generate additional MIME type versions. ([415](https://github.com/WordPress/performance/pull/415))
* Images: Discard WebP image if it is larger than corresponding JPEG image. ([418](https://github.com/WordPress/performance/pull/418))
* Images: Optimize computing dominant color and transparency for images by combining the two functions. ([381](https://github.com/WordPress/performance/pull/381))
* Images: Provide fallback JPEG images in frontend when WebP is not supported by the browser. ([360](https://github.com/WordPress/performance/pull/360))
* Images: Rely on `wp_get_image_editor()` methods argument to check whether it supports dominant color methods. ([404](https://github.com/WordPress/performance/pull/404))
* Images: Remove experimental label from Dominant Color module and turn on by default for new installs. ([425](https://github.com/WordPress/performance/pull/425))
* Site Health: Remove `perflab_aea_get_resource_file_size()` in favor of `wp_filesize()`. ([380](https://github.com/WordPress/performance/pull/380))
* Site Health: Update documentation link for autoloaded options. ([408](https://github.com/WordPress/performance/pull/408))
* Infrastructure: Implement mechanism to not load module if core version is available. ([390](https://github.com/WordPress/performance/pull/390))

**Bug Fixes**

* Images: Ensure incorrect usage of `webp_uploads_upload_image_mime_transforms` filter is treated correctly. ([393](https://github.com/WordPress/performance/pull/393))
* Images: Fix PHP notice and bug in logic for when `webp_uploads_prefer_smaller_image_file` filter is set to `true`. ([397](https://github.com/WordPress/performance/pull/397))
* Images: Fix an infinite loop in the WebP fallback mechanism. ([433](https://github.com/WordPress/performance/pull/433))
* Images: Fix dominant color upload process to not override potential third-party editors. ([401](https://github.com/WordPress/performance/pull/401))
* Images: Remove additional image backup sources & sizes files when attachment deleted. ([411](https://github.com/WordPress/performance/pull/411))
* Infrastructure: Avoid including .husky directory in plugin ZIP. ([421](https://github.com/WordPress/performance/pull/421))
* Infrastructure: Do not show admin pointer in multisite Network Admin. ([394](https://github.com/WordPress/performance/pull/394))

= 1.2.0 =

**Features**

* Images: Add Dominant Color module to provide color background for loading images. ([282](https://github.com/WordPress/performance/pull/282))
* Site Health: Add Site Health check for Full Page Cache usage. ([263](https://github.com/WordPress/performance/pull/263))

**Enhancements**

* Images: Update `webp_uploads_pre_generate_additional_image_source` filter to allow returning file size. ([334](https://github.com/WordPress/performance/pull/334))
* Infrastructure: Introduce plugin uninstaller routine. ([345](https://github.com/WordPress/performance/pull/345))
* Infrastructure: Use `wp_filesize` instead of `filesize` if available. ([376](https://github.com/WordPress/performance/pull/376))

**Bug Fixes**

* Images: Avoid overwriting existing WebP files when creating WebP images. ([359](https://github.com/WordPress/performance/pull/359))
* Images: Back up edited `full` image sources when restoring the original image. ([314](https://github.com/WordPress/performance/pull/314))

= 1.1.0 =

**Features**

* Infrastructure: Add Performance Lab generator meta tag to `wp_head` output. ([322](https://github.com/WordPress/performance/pull/322))

**Enhancements**

* Images: Introduce filter `webp_uploads_pre_generate_additional_image_source` to short-circuit generating additional image sources on upload. ([318](https://github.com/WordPress/performance/pull/318))
* Images: Introduce filter `webp_uploads_pre_replace_additional_image_source` to short-circuit replacing additional image sources in frontend content. ([319](https://github.com/WordPress/performance/pull/319))
* Images: Refine logic to select smaller image file in the frontend based on `webp_uploads_prefer_smaller_image_file` filter. ([302](https://github.com/WordPress/performance/pull/302))
* Images: Replace the featured image with WebP version when available. ([316](https://github.com/WordPress/performance/pull/316))
* Site Health: Update Site Health Autoloaded options documentation link. ([313](https://github.com/WordPress/performance/pull/313))
* Infrastructure: Avoid unnecessarily early escape of Site Health check labels. ([332](https://github.com/WordPress/performance/pull/332))

**Bug Fixes**

* Object Cache: Correct label for persistent object cache Site Health check. ([329](https://github.com/WordPress/performance/pull/329))
* Images: Only update the specified target images when an image is edited. ([301](https://github.com/WordPress/performance/pull/301))

= 1.0.0 =

**Features**

* Images: Generate secondary image MIME types when editing original image. ([235](https://github.com/WordPress/performance/pull/235))

**Enhancements**

* Images: Introduce `webp_uploads_prefer_smaller_image_file` filter allowing to opt in to preferring the smaller image file. ([287](https://github.com/WordPress/performance/pull/287))
* Images: Select MIME type to use in frontend content based on file size. ([243](https://github.com/WordPress/performance/pull/243))
* Site Health: Update Site Health reports copy for more clarity and consistency. ([272](https://github.com/WordPress/performance/pull/272))

**Documentation**

* Infrastructure: Define the plugin's version support and backward compatibility policy. ([240](https://github.com/WordPress/performance/pull/240))

= 1.0.0-rc.1 =

**Enhancements**

* Images: Change expected order of items in the `webp_uploads_content_image_mimes` filter. ([250](https://github.com/WordPress/performance/pull/250))
* Images: Replace images in frontend content without using an additional regular expression. ([262](https://github.com/WordPress/performance/pull/262))
* Images: Restore and backup image sizes alongside the sources properties. ([242](https://github.com/WordPress/performance/pull/242))

**Bug Fixes**

* Images: Select image editor based on WebP support instead of always using the default one. ([259](https://github.com/WordPress/performance/pull/259))

= 1.0.0-beta.3 =

**Bug Fixes**

* Infrastructure: Ensure default modules are loaded regardless of setting registration. ([248](https://github.com/WordPress/performance/pull/248))

= 1.0.0-beta.2 =

**Features**

* Images: Create additional MIME types for the full size image. ([194](https://github.com/WordPress/performance/pull/194))
* Site Health: Add module to warn about excessive amount of autoloaded options. ([124](https://github.com/WordPress/performance/pull/124))

**Enhancements**

* Images: Adds sources information to the attachment media details of the REST response. ([224](https://github.com/WordPress/performance/pull/224))
* Images: Allow developers to select which image format to use for images in the content. ([230](https://github.com/WordPress/performance/pull/230))
* Images: Allow developers to tweak which image formats to generate on upload. ([227](https://github.com/WordPress/performance/pull/227))
* Images: Replace the full size image in `the_content` with additional MIME type if available. ([195](https://github.com/WordPress/performance/pull/195))
* Object Cache: Include `memcached` extension in checks for object cache support. ([206](https://github.com/WordPress/performance/pull/206))
* Infrastructure: Add plugin banner and icon assets. ([231](https://github.com/WordPress/performance/pull/231))
* Infrastructure: Use `.gitattributes` instead of `.distignore` to better support ZIP creation. ([223](https://github.com/WordPress/performance/pull/223))

**Bug Fixes**

* Images: Use `original` image to generate all additional image format sub-sizes. ([207](https://github.com/WordPress/performance/pull/207))
* Infrastructure: Replace unreliable activation hook with default value for enabled modules. ([222](https://github.com/WordPress/performance/pull/222))

**Documentation**

* Infrastructure: Update release instructions to include proper branching strategy and protect release branches. ([221](https://github.com/WordPress/performance/pull/221))

= 1.0.0-beta.1 =

**Features**

* Images: Add WebP for uploads module. ([32](https://github.com/WordPress/performance/pull/32))
* Images: Support retry mechanism for generating sub-sizes in additional MIME types on constrained environments. ([188](https://github.com/WordPress/performance/pull/188))
* Images: Update `the_content` with the appropiate image format. ([152](https://github.com/WordPress/performance/pull/152))
* Site Health: Add WebP support in site health. ([141](https://github.com/WordPress/performance/pull/141))
* Site Health: Add module to alert about excessive JS and CSS assets. ([54](https://github.com/WordPress/performance/pull/54))
* Object Cache: Add Site Health check module for persistent object cache. ([111](https://github.com/WordPress/performance/pull/111))
* Infrastructure: Add settings screen to toggle modules. ([30](https://github.com/WordPress/performance/pull/30))
* Infrastructure: Added admin pointer. ([199](https://github.com/WordPress/performance/pull/199))

**Enhancements**

* Object Cache: Always recommend object cache on multisite. ([200](https://github.com/WordPress/performance/pull/200))
* Images: Create image sub-sizes in additional MIME types using `sources` for storage. ([147](https://github.com/WordPress/performance/pull/147))
* Images: Update module directories to be within their focus directory. ([58](https://github.com/WordPress/performance/pull/58))
* Site Health: Enhance detection of enqueued frontend assets. ([136](https://github.com/WordPress/performance/pull/136))
* Infrastructure: Add link to Settings screen to the plugin's entry in plugins list table. ([197](https://github.com/WordPress/performance/pull/197))
* Infrastructure: Enable all non-experimental modules on plugin activation. ([191](https://github.com/WordPress/performance/pull/191))
* Infrastructure: Include generated module-i18n.php file in repository. ([196](https://github.com/WordPress/performance/pull/196))
* Infrastructure: Introduce `perflab_active_modules` filter to control which modules are active. ([87](https://github.com/WordPress/performance/pull/87))
* Infrastructure: Remove unnecessary question marks from checkbox labels. ([110](https://github.com/WordPress/performance/pull/110))
* Infrastructure: Rename `object-caching` to `object-cache`. ([108](https://github.com/WordPress/performance/pull/108))

**Bug Fixes**

* Images: Ensure the `-scaled` image remains in the original uploaded format. ([143](https://github.com/WordPress/performance/pull/143))
* Images: Fix typo to access to the correct image properties. ([203](https://github.com/WordPress/performance/pull/203))
* Infrastructure: Ensure that module header fields can be translated. ([60](https://github.com/WordPress/performance/pull/60))

**Documentation**

* Site Health: Mark Site Health Audit Enqueued Assets module as experimental for now. ([205](https://github.com/WordPress/performance/pull/205))
* Infrastructure: Add `readme.txt` and related update script. ([72](https://github.com/WordPress/performance/pull/72))
* Infrastructure: Add changelog generator script. ([51](https://github.com/WordPress/performance/pull/51))
* Infrastructure: Add contribution documentation. ([47](https://github.com/WordPress/performance/pull/47))
* Infrastructure: Add release documentation. ([138](https://github.com/WordPress/performance/pull/138))
* Infrastructure: Define module specification in documentation. ([26](https://github.com/WordPress/performance/pull/26))
