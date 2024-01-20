<?php
/**
 * Module Name: Partytown Web Worker
 * Description: Run JavaScript in a separate Web Worker with the help of Partytown library.
 * Experimental: Yes
 *
 * @since   n.e.x.t
 * @package performance-lab
 */

/**
 * PartyTown Configuration
 *
 * @since n.e.x.t
 * @see https://partytown.builder.io/configuration
 * @return array
 */
function perflab_web_worker_partytown_configuration() {
	$config = array(
		'lib'     => str_replace( site_url(), '', plugin_dir_url( __FILE__ ) ) . 'assets/js/partytown/',
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
function perflab_web_worker_partytown_init() {
	wp_register_script(
		'partytown',
		plugin_dir_url( __FILE__ ) . 'assets/js/partytown/partytown.js',
		array(),
		PERFLAB_VERSION,
		array( 'in_footer' => false )
	);

	wp_add_inline_script(
		'partytown',
		sprintf(
			'window.partytown = %s;',
			wp_json_encode( perflab_web_worker_partytown_configuration() )
		),
		'before'
	);

	wp_enqueue_script( 'partytown' );
}
add_action( 'wp_enqueue_scripts', 'perflab_web_worker_partytown_init', defined( 'PHP_INT_MIN' ) ? PHP_INT_MIN : ~ PHP_INT_MAX );

/**
 * Get all scripts tags which have a `partytown` dependency.
 *
 * @since n.e.x.t
 * @return void
 */
function perflab_web_worker_partytown_worker_scripts() {
	$partytown_handles = perflab_get_partytown_handles();

	foreach ( $partytown_handles as $partytown_handle ) {
		add_filter(
			'script_loader_tag',
			/**
			 * Add type="text/partytown" to script tag.
			 *
			 * @since n.e.x.t
			 * @param string $tag Script tag.
			 * @param string $handle Script handle.
			 * @param string $src Script source.
			 * @param string $partytown_handle Script handle which have `partytown` dependency.
			 *
			 * @return string $tag Script tag with type="text/partytown".
			 */
			static function ( $tag, $handle, $src ) use ( $partytown_handle ) {
				if ( $handle === $partytown_handle ) {
					$create_script_tag = sprintf(
						'<script type="text/partytown" src="%1s" id="%2s"></script>',
						$src,
						$handle . '-js'
					);
					$tag               = $create_script_tag;
				}
				return $tag;
			},
			10,
			3
		);
	}
}
add_action( 'wp_print_scripts', 'perflab_web_worker_partytown_worker_scripts' );

/**
 * Helper function to get all scripts tags which has `partytown` dependency.
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
