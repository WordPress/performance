=== Performance Lab ===

Contributors:      performanceteam
Requires at least: 5.8
Tested up to:      5.9
Requires PHP:      5.6
Stable tag:        1.0.0-beta.1
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Tags:              performance, images, javascript, site health, measurement, object caching

Performance plugin from the WordPress Performance Group, which is a collection of standalone performance modules.

== Description ==

The Performance Lab plugin is a collection of modules focused on enhancing performance of your site, most of which should eventually be merged into WordPress core. The plugin allows to individually enable and test the modules to get their benefits before they become available in WordPress core, and to provide feedback to further improve the solutions.

Currently the plugin includes the following performance modules:

* **WebP Uploads:** Uses WebP as the default format for new JPEG image uploads if the server supports it.

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

= 1.0.0-beta.1 =

**Features**

* Images: Add WebP for uploads module. ([32](https://github.com/WordPress/performance/pull/32))
* Infrastructure: Add settings screen to toggle modules. ([30](https://github.com/WordPress/performance/pull/30))

**Enhancements**

* Images: Update module directories to be within their focus directory. ([58](https://github.com/WordPress/performance/pull/58))

**Bug Fixes**

* Infrastructure: Ensure that module header fields can be translated. ([60](https://github.com/WordPress/performance/pull/60))

**Documentation**

* Infrastructure: Add changelog generator script. ([51](https://github.com/WordPress/performance/pull/51))
* Infrastructure: Add contribution documentation. ([47](https://github.com/WordPress/performance/pull/47))
* Infrastructure: Define module specification in documentation. ([26](https://github.com/WordPress/performance/pull/26))
