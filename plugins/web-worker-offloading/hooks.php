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
 * Registers defaults scripts for Web Worker Offloading.
 *
 * @since 0.1.0
 *
 * @param WP_Scripts $scripts WP_Scripts instance.
 */
function wwo_register_default_scripts( WP_Scripts $scripts ): void {
	$partytown_js = file_get_contents( __DIR__ . '/build/partytown.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- It's a local filesystem path not a remote request.
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
			'window.partytown = %s;',
			wp_json_encode( wwo_get_configuration() )
		),
		'before'
	);

	$scripts->add_inline_script( 'web-worker-offloading', $partytown_js );
}
add_action( 'wp_default_scripts', 'wwo_register_default_scripts' );

/**
 * Prepends web-worker-offloading to the list of scripts to print if one of the queued scripts is offloaded to a worker.
 *
 * This also marks their strategy as `async`. This is needed because scripts offloaded to a worker thread can be
 * considered async. However, they may include `before` and `after` inline scripts that need sequential execution. Once
 * marked as async, `filter_eligible_strategies()` determines if the script is eligible for async execution. If so, it
 * will be offloaded to the worker thread.
 *
 * @since 0.1.0
 *
 * @param string[]|mixed $script_handles An array of enqueued script dependency handles.
 * @return string[] Script handles.
 */
function wwo_filter_print_scripts_array( $script_handles ): array {
	$scripts = wp_scripts();
	foreach ( (array) $script_handles as $handle ) {
		if ( true === (bool) $scripts->get_data( $handle, 'worker' ) ) {
			$scripts->set_group( 'web-worker-offloading', false, 0 ); // Try to print in the head.
			array_unshift( $script_handles, 'web-worker-offloading' );

			// TODO: This should be reconsidered because scripts needing to be offloaded will often have after scripts. See <https://github.com/WordPress/performance/pull/1497/files#r1733538721>.
			if ( false === wp_scripts()->get_data( $handle, 'strategy' ) ) {
				wp_script_add_data( $handle, 'strategy', 'async' ); // The 'defer' strategy would work as well.
				wp_script_add_data( $handle, 'wwo_strategy_added', true );
			}
		}
	}
	return $script_handles;
}
add_filter( 'print_scripts_array', 'wwo_filter_print_scripts_array' );

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
		(bool) wp_scripts()->get_data( $handle, 'worker' )
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
