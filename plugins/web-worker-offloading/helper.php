<?php
/**
 * Helpers for Web Worker Offloading.
 *
 * @since 0.1.0
 * @package web-worker-offloading
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Gets configuration for Web Worker Offloading.
 *
 * @since 0.1.0
 * @link https://partytown.builder.io/configuration
 * @link https://github.com/BuilderIO/partytown/blob/b292a14047a0c12ca05ba97df1833935d42fdb66/src/lib/types.ts#L393-L548
 *
 * @return array<string, mixed> Configuration for Partytown.
 */
function plwwo_get_configuration(): array {
	$config = array(
		// The source code in the build directory is compiled from <https://github.com/BuilderIO/partytown/tree/main/src/lib>.
		// See webpack config in the WordPress/performance repo: <https://github.com/WordPress/performance/blob/282a068f3eb2575d37aeb9034e894e7140fcddca/webpack.config.js#L84-L130>.
		'lib' => wp_parse_url( plugin_dir_url( __FILE__ ), PHP_URL_PATH ) . 'build/',
	);

	if ( WP_DEBUG && SCRIPT_DEBUG ) {
		$config['debug'] = true;
	}

	/**
	 * Add configuration for Web Worker Offloading.
	 *
	 * Many of the configuration options are not documented publicly, so refer to the TypeScript definitions.
	 * Additionally, not all of the configuration options (e.g. functions) can be serialized as JSON and must instead be
	 * defined in JavaScript instead. To do so, use the following PHP code instead of filtering `plwwo_configuration`:
	 *
	 *     add_action(
	 *         'wp_enqueue_scripts',
	 *         function () {
	 *             wp_add_inline_script(
	 *                 'web-worker-offloading',
	 *                 <<<JS
	 *                 window.partytown = {
	 *                     ...(window.partytown || {}),
	 *                     resolveUrl: (url, location, type) => {
	 *                         if (type === 'script') {
	 *                             const proxyUrl = new URL('https://my-reverse-proxy.example.com/');
	 *                             proxyUrl.searchParams.append('url', url.href);
	 *                             return proxyUrl;
	 *                         }
	 *                         return url;
	 *                     },
	 *                 };
	 *                 JS,
	 *                 'before'
	 *             );
	 *         }
	 *     );
	 *
	 * There are also many configuration options which are not documented, so refer to the TypeScript definitions.
	 *
	 * @since 0.1.0
	 * @link https://partytown.builder.io/configuration
	 * @link https://github.com/BuilderIO/partytown/blob/b292a14047a0c12ca05ba97df1833935d42fdb66/src/lib/types.ts#L393-L548
	 *
	 * @param array<string, mixed> $config Configuration for Partytown.
	 */
	return (array) apply_filters( 'plwwo_configuration', $config );
}
