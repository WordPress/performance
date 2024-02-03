<?php
/**
 * Module Name: Partytown Web Worker
 * Description: Add support for running JavaScript in a separate Web Worker with the help of the Partytown library.
 * Experimental: Yes
 *
 * @since   n.e.x.t
 * @package performance-lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * PartyTown Configuration
 *
 * @since n.e.x.t
 * @see https://partytown.builder.io/configuration
 * @return array
 */
function perflab_partytown_web_worker_configuration() {
	$plugin_dir           = untrailingslashit( plugin_dir_path( __FILE__ ) );
	$content_dir_basename = basename( WP_CONTENT_DIR );

	$config = array(
		'lib'     => '/' . substr( $plugin_dir, strpos( $plugin_dir, $content_dir_basename ) ) . '/assets/js/partytown/',
		'forward' => array(),
	);

	/**
	 * Add configuration for PartyTown.
	 *
	 * @since n.e.x.t
	 * @see <https://partytown.builder.io/configuration>.
	 * @param array $config Configuration for PartyTown.
	 * @return array
	 */
	return apply_filters( 'perflab_partytown_configuration', $config );
}

/**
 * Initialize PartyTown
 *
 * @since n.e.x.t
 * @return void
 */
function perflab_partytown_web_worker_init() {
	$partytown_js = file_get_contents( __DIR__ . '/assets/js/partytown/partytown.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- It's a local filesystem path not a remote request.

	wp_register_script(
		'partytown',
		'',
		array(),
		PERFLAB_VERSION,
		array( 'in_footer' => false )
	);

	wp_add_inline_script(
		'partytown',
		sprintf(
			'window.partytown = %s;',
			wp_json_encode( perflab_partytown_web_worker_configuration() )
		),
		'before'
	);

	wp_add_inline_script(
		'partytown',
		$partytown_js,
		'after'
	);
}
add_action( 'wp_enqueue_scripts', 'perflab_partytown_web_worker_init' );

/**
 * Helper function to get all scripts tags which has `partytown` dependency.
 *
 * @since n.e.x.t
 *
 * @return array Array of script handles with `partytown` dependency.
 */
function perflab_get_partytown_handles() {
	global $wp_scripts;

	$partytown_handles = array();
	foreach ( $wp_scripts->registered as $handle => $script ) {
		if ( ! empty( $script->deps ) && in_array( 'partytown', $script->deps, true ) ) {
			$partytown_handles[] = $handle;
		}
	}

	return $partytown_handles;
}

/**
 * Update script type for handles having `partytown` as dependency.
 *
 * @since n.e.x.t
 *
 * @param string $tag Script tag.
 * @param string $handle Script handle.
 * @param string $src Script source.
 *
 * @return string $tag Script tag with type="text/partytown".
 */
function perflab_partytown_web_worker_update_script_type( $tag, $handle, $src ) {
	global $wp_scripts;

	$partytown_handles = perflab_get_partytown_handles();

	if ( in_array( $handle, $partytown_handles, true ) ) {
		$before_script = $wp_scripts->get_inline_script_data( $handle, 'before' );
		$after_script  = $wp_scripts->get_inline_script_data( $handle, 'after' );

		if ( ! empty( $before_script ) || ! empty( $after_script ) ) {
			_doing_it_wrong(
				'wp_add_inline_script',
				sprintf(
					/* translators: %s: script handle */
					esc_html__( 'Cannot add inline script "%s" to scripts with a "partytown" dependency. Script will continue to load in the main thread.', 'performance-lab' ),
					'<a href="https://developer.wordpress.org/reference/functions/wp_add_inline_script/">wp_add_inline_script()</a>'
				),
				esc_html( PERFLAB_VERSION )
			);
		} else {
			$tag = wp_get_script_tag(
				array(
					'type'   => 'text/partytown',
					'src'    => $src,
					'handle' => $handle . '-js',
				)
			);
		}
	}

	return $tag;
}
add_filter( 'script_loader_tag', 'perflab_partytown_web_worker_update_script_type', 10, 3 );
