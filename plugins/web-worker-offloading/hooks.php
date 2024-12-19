<?php
/**
 * Hook callback for Web Worker Offloading.
 *
 * @since 0.1.0
 * @package web-worker-offloading
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
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
		$partytown_js_path = '/build/debug/partytown.js';
	} else {
		$partytown_js_path = '/build/partytown.js';
	}

	$partytown_js = file_get_contents( __DIR__ . $partytown_js_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- It's a local filesystem path not a remote request.
	if ( false === $partytown_js ) {
		return;
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
add_action( 'wp_default_scripts', 'plwwo_register_default_scripts' );

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
add_filter( 'print_scripts_array', 'plwwo_filter_print_scripts_array', PHP_INT_MAX );

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
add_filter( 'script_loader_tag', 'plwwo_update_script_type', 10, 2 );

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
add_filter( 'wp_inline_script_attributes', 'plwwo_filter_inline_script_attributes' );

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
add_action( 'wp_head', 'plwwo_render_generator_meta_tag' );
