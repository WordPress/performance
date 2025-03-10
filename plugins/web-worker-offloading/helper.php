<?php
/**
 * Helpers for Web Worker Offloading.
 *
 * @since 0.1.0
 * @package web-worker-offloading
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

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
		'lib' => wp_parse_url( plugins_url( 'build/', __FILE__ ), PHP_URL_PATH ),
	);

	if ( WP_DEBUG && SCRIPT_DEBUG ) {
		$config['debug'] = true;// @codeCoverageIgnore
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

/**
 * Registers defaults scripts for Web Worker Offloading.
 *
 * @since 0.1.0
 * @access private
 *
 * @param WP_Scripts $scripts WP_Scripts instance.
 */
function plwwo_register_default_scripts( WP_Scripts $scripts ): void {
	// The source code for partytown.js is built from <https://github.com/BuilderIO/partytown/blob/b292a14047a0c12ca05ba97df1833935d42fdb66/src/lib/main/snippet.ts>.
	// See webpack config in the WordPress/performance repo: <https://github.com/WordPress/performance/blob/282a068f3eb2575d37aeb9034e894e7140fcddca/webpack.config.js#L84-L130>.
	if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
		$partytown_js_path = '/build/debug/partytown.js';// @codeCoverageIgnore
	} else {
		$partytown_js_path = '/build/partytown.js';
	}

	$partytown_js = file_get_contents( __DIR__ . $partytown_js_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- It's a local filesystem path not a remote request.
	if ( false === $partytown_js ) {
		return;// @codeCoverageIgnore
	}

	$scripts->add(
		'web-worker-offloading',
		'',
		array(),
		WEB_WORKER_OFFLOADING_VERSION,
		array( 'in_footer' => false )
	);

	$scripts->add_inline_script(
		'web-worker-offloading',
		sprintf(
			'window.partytown = {...(window.partytown || {}), ...%s};',
			wp_json_encode( plwwo_get_configuration() )
		),
		'before'
	);

	$scripts->add_inline_script( 'web-worker-offloading', $partytown_js );
}

/**
 * Prepends web-worker-offloading to the list of scripts to print if one of the queued scripts is offloaded to a worker.
 *
 * @since 0.1.0
 * @access private
 *
 * @param string[]|mixed $script_handles An array of enqueued script dependency handles.
 * @return string[] Script handles.
 */
function plwwo_filter_print_scripts_array( $script_handles ): array {
	$scripts = wp_scripts();
	foreach ( (array) $script_handles as $handle ) {
		if ( true === (bool) $scripts->get_data( $handle, 'worker' ) ) {
			$scripts->set_group( 'web-worker-offloading', false, 0 ); // Try to print in the head.
			array_unshift( $script_handles, 'web-worker-offloading' );
			break;
		}
	}
	return $script_handles;
}

/**
 * Updates script type for handles having `web-worker-offloading` as dependency.
 *
 * @since 0.1.0
 * @access private
 *
 * @param string|mixed $tag    Script tag.
 * @param string       $handle Script handle.
 * @return string|mixed Script tag with type="text/partytown" for eligible scripts.
 */
function plwwo_update_script_type( $tag, string $handle ) {
	if (
		is_string( $tag )
		&&
		(bool) wp_scripts()->get_data( $handle, 'worker' )
	) {
		$html_processor = new WP_HTML_Tag_Processor( $tag );
		while ( $html_processor->next_tag( array( 'tag_name' => 'SCRIPT' ) ) ) {
			if ( $html_processor->get_attribute( 'id' ) === "{$handle}-js" ) {
				$html_processor->set_attribute( 'type', 'text/partytown' );
				$tag = $html_processor->get_updated_html();
				break;
			}
		}
	}
	return $tag;
}

/**
 * Filters inline script attributes to offload to a worker if the script has been opted-in.
 *
 * @since 0.1.0
 * @access private
 *
 * @param array<string, mixed>|mixed $attributes Attributes.
 * @return array<string, mixed> Attributes.
 */
function plwwo_filter_inline_script_attributes( $attributes ): array {
	$attributes = (array) $attributes;
	if (
		isset( $attributes['id'] )
		&&
		1 === preg_match( '/^(?P<handle>.+)-js-(?:before|after)$/', $attributes['id'], $matches )
		&&
		(bool) wp_scripts()->get_data( $matches['handle'], 'worker' )
	) {
		$attributes['type'] = 'text/partytown';
	}
	return $attributes;
}

/**
 * Displays the HTML generator meta tag for the Web Worker Offloading plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since 0.1.1
 */
function plwwo_render_generator_meta_tag(): void {
	// Use the plugin slug as it is immutable.
	echo '<meta name="generator" content="web-worker-offloading ' . esc_attr( WEB_WORKER_OFFLOADING_VERSION ) . '">' . "\n";
}
