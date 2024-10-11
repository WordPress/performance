=== Web Worker Offloading ===

Contributors:      wordpressdotorg
Tested up to:      6.6
Stable tag:        0.1.0
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Tags:              performance, JavaScript, web worker, partytown, analytics

Offloads select JavaScript execution to a Web Worker to reduce work on the main thread and improve the Interaction to Next Paint (INP) metric.

== Description ==

This plugin offloads JavaScript execution to a Web Worker, improving performance by freeing up the main thread. This should translate into improved [Interaction to Next Paint](https://web.dev/articles/inp) (INP) scores.

⚠ _This functionality is experimental._ ⚠

In order to opt in a script to be loaded in a worker, simply add `worker` script data to a registered script. For example,
if you have a script registered with the handle of `foo`, opt-in to offload it to a web worker by doing:

`
wp_script_add_data( 'foo', 'worker', true );
`

Unlike with the script loading strategies (async/defer), any inline before/after scripts associated with the worker-offloaded registered script will also be offloaded to the worker, whereas with the script strategies an inline after script would block the script from being delayed.

Otherwise, the plugin currently ships with built-in integrations to offload Google Analytics to a web worker for the following plugin:

* [WooCommerce](https://wordpress.org/plugins/woocommerce/)

Support for [Site Kit by Google](https://wordpress.org/plugins/google-site-kit/) and [Rank Math SEO](https://wordpress.org/plugins/seo-by-rank-math/) are [planned](https://github.com/WordPress/performance/issues/1455).

Please monitor your analytics once activating to ensure all the expected events are being logged. At the same time, monitor your INP scores to check for improvement.

This plugin relies on the [Partytown 🎉](https://partytown.builder.io/) library by Builder.io, released under the MIT license. This library is in beta and there are quite a few [open bugs](https://github.com/BuilderIO/partytown/issues?q=is%3Aopen+is%3Aissue+label%3Abug).

The [Partytown configuration](https://partytown.builder.io/configuration) can be modified via the `plwwo_configuration` filter. For example:

`
<?php
add_filter( 'plwwo_configuration', function ( $config ) {
	$config['mainWindowAccessors'][] = 'wp'; // Make the wp global available in the worker (e.g. wp.i18n and wp.hooks).
	return $config;
} );
`

However, not all of the configuration options can be serialized to JSON in this way, for example the `resolveUrl` configuration is a function. To specify this, you can add an inline script as follows.

`
<?php
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_add_inline_script(
			'web-worker-offloading',
			<<<JS
			window.partytown = {
				...(window.partytown || {}),
				resolveUrl: (url, location, type) => {
					if (type === 'script') {
						const proxyUrl = new URL('https://my-reverse-proxy.example.com/');
						proxyUrl.searchParams.append('url', url.href);
						return proxyUrl;
					}
					return url;
				},
			};
			JS,
			'before'
		);
	}
);
`

There are also many configuration options which are not documented, so refer to the [TypeScript definitions](https://github.com/BuilderIO/partytown/blob/b292a14047a0c12ca05ba97df1833935d42fdb66/src/lib/types.ts#L393-L548).

== Frequently Asked Questions ==

= Why are my offloaded scripts not working and I see a 404 error in the console for `partytown-sandbox-sw.html`? =

If you find that your offloaded scripts aren't working while also seeing a 404 error in the console for a file at `/wp-content/plugins/web-worker-offloading/build/partytown-sandbox-sw.html?1727389399791` then it's likely you have Chrome DevTools open with the "Bypass for Network" toggle enabled in the Application panel.

= Where can I report security bugs? =

The Performance team and WordPress community take security bugs seriously. We appreciate your efforts to responsibly disclose your findings, and will make every effort to acknowledge your contributions.

To report a security issue, please visit the [WordPress HackerOne](https://hackerone.com/wordpress) program.

= How can I contribute to the plugin? =

Contributions are always welcome! Learn more about how to get involved in the [Core Performance Team Handbook](https://make.wordpress.org/performance/handbook/get-involved/).

The [plugin source code](https://github.com/WordPress/performance/tree/trunk/plugins/web-worker-offloading) is located in the [WordPress/performance](https://github.com/WordPress/performance) repo on GitHub.

== Changelog ==

= 0.1.0 =

* Initial release.
