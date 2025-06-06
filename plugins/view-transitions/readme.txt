=== View Transitions ===

Contributors: wordpressdotorg
Tested up to: 6.8
Stable tag:   1.0.1
License:      GPLv2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Tags:         performance, view transitions, smooth transitions, animations

Adds smooth transitions between navigations to your WordPress site.

== Description ==

This plugin implements support for [cross-document view transitions](https://developer.chrome.com/docs/web-platform/view-transitions/cross-document) in WordPress. This effectively replaces the hard transitions when navigating from one URL to the other with a smooth animation, by default using a fade effect.

= Browser support =

Cross-document view transitions are supported in a variety of browsers, including Chrome, Edge, and Safari. Users with browsers that currently do not support it should not see any adverse effects when the plugin is active. They will simply not benefit from the feature and continue to experience the traditional hard transitions between URLs.

[Please refer to "Can I use..." for a comprehensive overview of browser support for the feature.](https://caniuse.com/mdn-css_at-rules_view-transition)

== Installation ==

= Installation from within WordPress =

1. Visit **Plugins > Add New**.
2. Search for **View Transitions**.
3. Install and activate the **View Transitions** plugin.

= Manual installation =

1. Upload the entire plugin folder to the `/wp-content/plugins/` directory.
2. Visit **Plugins**.
3. Activate the **View Transitions** plugin.

== Frequently Asked Questions ==

= Where can I submit my plugin feedback? =

Feedback is encouraged and much appreciated, especially since this plugin may contain future WordPress core features. If you have suggestions or requests for new features, you can [submit them as an issue in the WordPress Performance Team's GitHub repository](https://github.com/WordPress/performance/issues/new/choose). If you need help with troubleshooting or have a question about the plugin, please [create a new topic on our support forum](https://wordpress.org/support/plugin/view-transitions/#new-topic-0).

= Where can I report security bugs? =

The Performance team and WordPress community take security bugs seriously. We appreciate your efforts to responsibly disclose your findings, and will make every effort to acknowledge your contributions.

To report a security issue, please visit the [WordPress HackerOne](https://hackerone.com/wordpress) program.

= How can I contribute to the plugin? =

Contributions are always welcome! Learn more about how to get involved in the [Core Performance Team Handbook](https://make.wordpress.org/performance/handbook/get-involved/).

== Changelog ==

= 1.0.1 =

* Fix view transitions setting values not being saved. ([2036](https://github.com/WordPress/performance/pull/2036))

= 1.0.0 =

* Initial release. ([1997](https://github.com/WordPress/performance/issues/1997))
