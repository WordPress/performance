=== Speculation Rules ===

Contributors:      wordpressdotorg
Requires at least: 6.3
Tested up to:      6.4
Requires PHP:      7.0
Stable tag:        1.0.1
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Tags:              performance, javascript, speculation rules, prerender, prefetch

Uses the Speculation Rules API to prerender linked URLs upon hover by default.

== Description ==

This plugin adds support for the [Speculation Rules API](https://developer.mozilla.org/en-US/docs/Web/API/Speculation_Rules_API), which allows defining rules by which certain URLs are dynamically prefetched or prerendered based on user interaction.

See the [Speculation Rules WICG specification draft](https://wicg.github.io/nav-speculation/speculation-rules.html).

By default, the plugin is configured to prerender WordPress frontend URLs when the user hovers over a relevant link. This can be customized via the "Speculation Rules" section under _Settings > Reading_.

A filter can be used to exclude certain URL paths from being eligible for prefetching and prerendering (see FAQ section). Alternatively, you can add the 'no-prerender' CSS class to any link (`<a>` tag) that should not be prerendered.

= Browser support =

The Speculation Rules API is a new web API, and the specific syntax used by the plugin currently requires using Chrome 121+.

Other browsers will not see any adverse effects, however the feature will not work for those clients.

* [Browser support for the Speculation Rules API in general](https://caniuse.com/mdn-html_elements_script_type_speculationrules)
* [Information on document rules syntax support used by the plugin](https://developer.chrome.com/blog/chrome-121-beta#speculation_rules_api)

== Installation ==

= Installation from within WordPress =

1. Visit **Plugins > Add New**.
2. Search for **Speculation Rules**.
3. Install and activate the **Speculation Rules** plugin.

= Manual installation =

1. Upload the entire `speculation-rules` folder to the `/wp-content/plugins/` directory.
2. Visit **Plugins**.
3. Activate the **Speculation Rules** plugin.

== Frequently Asked Questions ==

= How can I prevent certain URLs from being prefetched and prerendered? =

Not every URL can be reasonably prerendered. Prerendering static content is typically reliable, however prerendering interactive content, such as a logout URL, can lead to issues. For this reason, certain WordPress core URLs such as `/wp-login.php` and `/wp-admin/*` are excluded from prefetching and prerendering. You can exclude additional URL patterns by using the `plsr_speculation_rules_href_exclude_paths` filter.

This example would ensure that URLs like `https://example.com/cart/` or `https://example.com/cart/foo` would be excluded from prefetching and prerendering.
`
<?php

add_filter(
	'plsr_speculation_rules_href_exclude_paths',
	function ( $exclude_paths ) {
		$exclude_paths[] = '/cart/*';
		return $exclude_paths;
	}
);
`

= Where can I submit my plugin feedback? =

Feedback is encouraged and much appreciated, especially since this plugin may contain future WordPress core features. If you have suggestions or requests for new features, you can [submit them as an issue in the WordPress Performance Team's GitHub repository](https://github.com/WordPress/performance/issues/new/choose). If you need help with troubleshooting or have a question about the plugin, please [create a new topic on our support forum](https://wordpress.org/support/plugin/speculation-rules/#new-topic-0).

= Where can I report security bugs? =

The Performance team and WordPress community take security bugs seriously. We appreciate your efforts to responsibly disclose your findings, and will make every effort to acknowledge your contributions.

To report a security issue, please visit the [WordPress HackerOne](https://hackerone.com/wordpress) program.

= How can I contribute to the plugin? =

Contributions are always welcome! Learn more about how to get involved in the [Core Performance Team Handbook](https://make.wordpress.org/performance/handbook/get-involved/).

== Changelog ==

= 1.0.1 =

* Escape path prefix and restrict it to be a pathname in Speculation Rules. ([951](https://github.com/WordPress/performance/pull/951))
* Force HTML5 script theme support when printing JSON script. ([952](https://github.com/WordPress/performance/pull/952))
* Add icon and banner assets for plugin directory. ([987](https://github.com/WordPress/performance/pull/987))

= 1.0.0 =

* Initial release of the Speculation Rules plugin as a standalone plugin. ([733](https://github.com/WordPress/performance/pull/733))
