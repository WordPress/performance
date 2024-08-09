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
 * Gets configuration for Web Worker Offloading.
 *
 * @since 0.1.0
 * @link https://partytown.builder.io/configuration
 *
 * @return array{ debug?: bool, forward?: non-empty-string[], lib: non-empty-string, loadScriptsOnMainThread?: non-empty-string[], nonce?: non-empty-string } Configuration for Partytown.
 */
function wwo_get_configuration(): array {
	$config = array(
		'lib'     => wp_parse_url( plugin_dir_url( __FILE__ ), PHP_URL_PATH ) . 'build/',
		'forward' => array(),
	);

	/**
	 * Add configuration for Web Worker Offloading.
	 *
	 * @since 0.1.0
	 * @link https://partytown.builder.io/configuration
	 *
	 * @param array{ debug?: bool, forward?: non-empty-string[], lib: non-empty-string, loadScriptsOnMainThread?: non-empty-string[], nonce?: non-empty-string } $config Configuration for Partytown.
	 */
	return apply_filters( 'wwo_configuration', $config );
}

/**
 * Initializes Web Worker Offloading.
 *
 * @since 0.1.0
 */
function wwo_init(): void {
	$partytown_js = file_get_contents( __DIR__ . '/build/partytown.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- It's a local filesystem path not a remote request.
	if ( false === $partytown_js ) {
		return;
	}

	wp_register_script(
		'web-worker-offloading',
		'',
		array(),
		WEB_WORKER_OFFLOADING_VERSION,
		array( 'in_footer' => false )
	);

	wp_add_inline_script(
		'web-worker-offloading',
		sprintf(
			'window.partytown = %s;',
			wp_json_encode( wwo_get_configuration() )
		),
		'before'
	);

	wp_add_inline_script( 'web-worker-offloading', $partytown_js );
}
add_action( 'wp_enqueue_scripts', 'wwo_init' );

/**
 * Marks scripts with `web-worker-offloading` dependency as async.
 *
 * This is needed because scripts offloaded to a worker thread can be considered async. However, they may include `before` and `after` inline
 * scripts that need sequential execution. Once marked as async, `filter_eligible_strategies()` determines if the
 * script is eligible for async execution. If so, it will be offloaded to the worker thread.
 *
 * @since 0.1.0
 *
 * @param string[]|mixed $script_handles Array of script handles.
 * @return string[] Array of script handles.
 */
function wwo_update_script_strategy( $script_handles ): array {
	$script_handles = array_intersect( (array) $script_handles, array_keys( wp_scripts()->registered ) );
	foreach ( $script_handles as $handle ) {
		if ( in_array( 'web-worker-offloading', wp_scripts()->registered[ $handle ]->deps, true ) ) {
			if ( false === wp_scripts()->get_data( $handle, 'strategy' ) ) {
				wp_script_add_data( $handle, 'strategy', 'async' ); // The 'defer' strategy would work as well.
				wp_script_add_data( $handle, 'wwo_strategy_added', true );
			}
		}
	}

	return $script_handles;
}
add_filter( 'print_scripts_array', 'wwo_update_script_strategy' );

/**
 * Updates script type for handles having `web-worker-offloading` as dependency.
 *
 * @since 0.1.0
 *
 * @param string|mixed $tag    Script tag.
 * @param string       $handle Script handle.
 * @return string|mixed Script tag with type="text/partytown" for eligible scripts.
 */
function wwo_update_script_type( $tag, string $handle ) {
	if (
		is_string( $tag ) &&
		array_key_exists( $handle, wp_scripts()->registered ) &&
		in_array( 'web-worker-offloading', wp_scripts()->registered[ $handle ]->deps, true )
	) {
		$html_processor = new WP_HTML_Tag_Processor( $tag );

		while ( $html_processor->next_tag( array( 'tag_name' => 'SCRIPT' ) ) ) {
			if ( $html_processor->get_attribute( 'id' ) !== "{$handle}-js" ) {
				continue;
			}
			if ( null === $html_processor->get_attribute( 'async' ) && null === $html_processor->get_attribute( 'defer' ) ) {
				_doing_it_wrong(
					'wwo_update_script_type',
					esc_html(
						sprintf(
							/* translators: %s: script handle */
							__( 'Unable to offload "%s" script to a worker. Script will continue to load in the main thread.', 'web-worker-offloading' ),
							$handle
						)
					),
					'Web Worker Offloading 0.1.0'
				);
			} else {
				$html_processor->set_attribute( 'type', 'text/partytown' );
			}
			if ( true === wp_scripts()->get_data( $handle, 'wwo_strategy_added' ) ) {
				$html_processor->remove_attribute( 'async' );
				$html_processor->remove_attribute( 'data-wp-strategy' );
			}
			$tag = $html_processor->get_updated_html();
		}
	}

	return $tag;
}
add_filter( 'script_loader_tag', 'wwo_update_script_type', 10, 2 );
