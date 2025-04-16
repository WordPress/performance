=== Speculative Loading ===

Contributors: wordpressdotorg
Tested up to: 6.8
Stable tag:   1.5.0
License:      GPLv2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Tags:         performance, javascript, speculation rules, prerender, prefetch

Enables browsers to speculatively prerender or prefetch pages to achieve near-instant loads based on user interaction.

== Description ==

This plugin adds support for the [Speculation Rules API](https://developer.mozilla.org/en-US/docs/Web/API/Speculation_Rules_API), which allows defining rules by which certain URLs are dynamically prefetched or prerendered.

See the [Speculation Rules WICG specification draft](https://wicg.github.io/nav-speculation/speculation-rules.html).

By default, the plugin is configured to prerender WordPress frontend URLs when the user interacts with a relevant link. This can be customized via the "Speculative Loading" section in the _Settings > Reading_ admin screen.

A filter can be used to exclude certain URL paths from being eligible for prefetching and prerendering (see FAQ section). Alternatively, you can add the `no-prerender` CSS class to any link (`<a>` tag) that should not be prerendered. See FAQ for more information.

= Browser support =

The Speculation Rules API is a new web API, and the functionality used by the plugin is supported in Chromium-based browsers such as Chrome, Edge, or Opera using version 121 or above. Other browsers such as Safari and Firefox will ignore the functionality with no ill effects; they will simply not benefit from the speculative loading. Note that certain browser extensions may disable preloading by default.

* [Browser support for the Speculation Rules API in general](https://caniuse.com/mdn-html_elements_script_type_speculationrules)
* [Information on document rules syntax support used by the plugin](https://developer.chrome.com/docs/web-platform/prerender-pages)

_This plugin was formerly known as Speculation Rules._

== Installation ==

= Installation from within WordPress =

1. Visit **Plugins > Add New**.
2. Search for **Speculative Loading**.
3. Install and activate the **Speculative Loading** plugin.

= Manual installation =

1. Upload the entire `speculation-rules` folder to the `/wp-content/plugins/` directory.
2. Visit **Plugins**.
3. Activate the **Speculative Loading** plugin.

== Frequently Asked Questions ==

= How can I prevent certain URLs from being prefetched and prerendered? =

Not every URL can be reasonably prerendered. Prerendering static content is typically reliable, however prerendering interactive content, such as a logout URL, can lead to issues. For this reason, certain WordPress core URLs such as `/wp-login.php` and `/wp-admin/*` are excluded from prefetching and prerendering. Additionally, any URLs generated with `wp_nonce_url()` (or which contains the `_wpnonce` query var) and `nofollow` links are also ignored. You can exclude additional URL patterns by using the `plsr_speculation_rules_href_exclude_paths` filter.

The following example ensures that URLs like `https://example.com/cart/` or `https://example.com/cart/foo` are excluded from prefetching and prerendering:
`
<?php
add_filter(
	'plsr_speculation_rules_href_exclude_paths',
	function ( array $exclude_paths ): array {
		$exclude_paths[] = '/cart/*';
		return $exclude_paths;
	}
);
`

Keep in mind that sometimes it may be useful to exclude a URL from prerendering while still allowing it to be prefetched. For example, a page with client-side JavaScript to update user state should probably not be prerendered, but it would be reasonable to prefetch.

For this purpose, the `plsr_speculation_rules_href_exclude_paths` filter receives the current mode (either "prefetch" or "prerender") to provide conditional exclusions.

The following example ensures that URLs like `https://example.com/products/...` cannot be prerendered, while still allowing them to be prefetched:
`
<?php
add_filter(
	'plsr_speculation_rules_href_exclude_paths',
	function ( array $exclude_paths, string $mode ): array {
		if ( 'prerender' === $mode ) {
			$exclude_paths[] = '/products/*';
		}
		return $exclude_paths;
	},
	10,
	2
);
`

As mentioned above, adding the `no-prerender` CSS class to a link will prevent it from being prerendered (but not prefetched). Additionally, links with `rel=nofollow` will neither be prefetched nor prerendered because some plugins add this to non-idempotent links (e.g. add to cart); such links ideally should rather be buttons which trigger a POST request or at least they should use `wp_nonce_url()`.

= Are there any special considerations for speculative loading behavior? =

For safety reasons, the entire speculative loading feature is disabled by default for logged-in users and for sites that do not use pretty permalinks. The latter is the case because plugins often use URLs with custom query parameters to let users perform actions, and such URLs should not be speculatively loaded. For sites without pretty permalinks, it is impossible or at least extremely complex to differentiate between which query parameters are Core defaults and which query parameters are custom.

If you are running this plugin on a site without pretty permalinks and are confident that there are no custom query parameters in use that can cause state changes, you can opt in to enabling speculative loading via the `plsr_enabled_without_pretty_permalinks` filter:

`
<?php
add_filter( 'plsr_enabled_without_pretty_permalinks', '__return_true' );
`

= How will this impact analytics and personalization? =

Prerendering can affect analytics and personalization.

For client-side JavaScript, is recommended to delay these until the prerender is activated (for example by clicking on the link). Some solutions (like Google Analytics) already do this automatically, see [Impact on Analytics](https://developer.chrome.com/docs/web-platform/prerender-pages#impact-on-analytics). Additionally, cross-origin iframes are not loaded until activation which can further avoid issues here.

Speculating with the default `moderate` eagerness decreases the risk that the prerendered page will not be visited by the user and therefore will avoid any side effects of loading such a link in advance. In contrast, `eager` speculation increases the risk that prerendered pages may not be loaded. Alternatively, the plugin offers to only speculate on mouse/pointer down (conservative) which reduces the risk even further and is an option for sites which are concerned about this, at the cost of having less of a lead time and so less of a performance gain.

A prerendered page is linked to the page that prerenders it, so personalisation may already be known by this point and changes (e.g. browsing other products, or logging in/out) often require a new page load, and hence a new prerender, which will then take these into account. But it definitely is something to be aware of and test! Prerendered pages can be canceled by removing the speculation rules `<script>` element from the page using standard JavaScript DOM APIs should this be needed when state changes without a new page load.

= Where can I submit my plugin feedback? =

Feedback is encouraged and much appreciated, especially since this plugin may contain future WordPress core features. If you have suggestions or requests for new features, you can [submit them as an issue in the WordPress Performance Team's GitHub repository](https://github.com/WordPress/performance/issues/new/choose). If you need help with troubleshooting or have a question about the plugin, please [create a new topic on our support forum](https://wordpress.org/support/plugin/speculation-rules/#new-topic-0).

= Where can I report security bugs? =

The Performance team and WordPress community take security bugs seriously. We appreciate your efforts to responsibly disclose your findings, and will make every effort to acknowledge your contributions.

To report a security issue, please visit the [WordPress HackerOne](https://hackerone.com/wordpress) program.

= How can I contribute to the plugin? =

Contributions are always welcome! Learn more about how to get involved in the [Core Performance Team Handbook](https://make.wordpress.org/performance/handbook/get-involved/).

== Changelog ==

= 1.5.0 =

**Enhancements**

* Add support for Speculative Loading WP Core API, loading the plugin's own API implementation conditionally. ([1883](https://github.com/WordPress/performance/pull/1883))

= 1.4.0 =

**Enhancements**

* Implement speculative loading considerations for safer behavior. ([1784](https://github.com/WordPress/performance/pull/1784))

= 1.3.1 =

**Bug Fixes**

* Check if rel contains nofollow instead of being just nofollow when excluding speculative loading. ([1232](https://github.com/WordPress/performance/pull/1232))

= 1.3.0 =

**Enhancements**

* Prevent speculatively loading links to the uploads, content, plugins, template, or stylesheet directories. ([1167](https://github.com/WordPress/performance/pull/1167))
* Facilitate embedding Speculative Loading in other plugins/themes. ([1159](https://github.com/WordPress/performance/pull/1159))
* Improve overall code quality with stricter static analysis checks. ([775](https://github.com/WordPress/performance/issues/775))
* Bump minimum PHP requirement to 7.2. ([1130](https://github.com/WordPress/performance/pull/1130))

= 1.2.2 =

**Bug Fixes**

* Fix composition of href exclude paths to account for JSON encoding and differing site/home URLs. ([1164](https://github.com/WordPress/performance/pull/1164))

**Documentation**

* Update readme with browser support and FAQ section about analytics and personalization. ([1155](https://github.com/WordPress/performance/pull/1155))

= 1.2.1 =

**Enhancements**

* Add settings link to Speculative Loading plugin action links. ([1145](https://github.com/WordPress/performance/pull/1145))
* Bump minimum PHP version to 7.2. ([1130](https://github.com/WordPress/performance/pull/1130))

**Bug Fixes**

* Exclude _wpnonce URLs in speculation rules. ([1143](https://github.com/WordPress/performance/pull/1143))
* Exclude rel=nofollow links from prefetch/prerender. ([1142](https://github.com/WordPress/performance/pull/1142))

= 1.2.0 =

* Add missing uninstall.php to remove plugin's database option. ([1128](https://github.com/WordPress/performance/pull/1128))

= 1.1.0 =

* Allow excluding URL patterns from prerendering or prefetching specifically. ([1025](https://github.com/WordPress/performance/pull/1025))
* Rename plugin to "Speculative Loading". ([1101](https://github.com/WordPress/performance/pull/1101))
* Add Speculative Loading generator tag. ([1102](https://github.com/WordPress/performance/pull/1102))
* Bump minimum required WP version to 6.4. ([1062](https://github.com/WordPress/performance/pull/1062))
* Update tested WordPress version to 6.5. ([1027](https://github.com/WordPress/performance/pull/1027))

= 1.0.1 =

* Escape path prefix and restrict it to be a pathname in Speculation Rules. ([951](https://github.com/WordPress/performance/pull/951))
* Force HTML5 script theme support when printing JSON script. ([952](https://github.com/WordPress/performance/pull/952))
* Add icon and banner assets for plugin directory. ([987](https://github.com/WordPress/performance/pull/987))

= 1.0.0 =

* Initial release of the Speculative Loading plugin as a standalone plugin. ([733](https://github.com/WordPress/performance/pull/733))
