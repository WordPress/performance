<?php
/**
 * Hook callbacks used for Autoloaded Options Health Check.
 *
 * @package performance-lab
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Adds test to site health.
 *
 * @since 1.0.0
 *
 * @param array $tests Site Health Tests.
 * @return array
 */
function perflab_aao_add_autoloaded_options_test( $tests ) {
	$tests['direct']['autoloaded_options'] = array(
		'label' => __( 'Autoloaded options', 'performance-lab' ),
		'test'  => 'perflab_aao_autoloaded_options_test',
	);
	return $tests;
}
add_filter( 'site_status_tests', 'perflab_aao_add_autoloaded_options_test' );

/**
 * Register custom REST API route for updating autoload.
 *
 * @since n.e.x.t
 */
function perflab_aao_register_rest_route() {
	register_rest_route(
		'perflab-aao/v1',
		'/update-autoload/(?P<option_name>[a-zA-Z0-9_-]+)',
		array(
			'methods'  => 'POST',
			'callback' => 'perflab_aao_update_autoload_rest',
		)
	);
}
add_action( 'rest_api_init', 'perflab_aao_register_rest_route' );

/**
 * Callback for updating autoload via REST API.
 *
 * @since n.e.x.t
 *
 * @param WP_REST_Request $request Request object.
 * @return WP_REST_Response
 */
function perflab_aao_update_autoload_rest( $request ) {
	$option_name = $request->get_param( 'option_name' );
	$autoload    = $request->get_param( 'autoload' );
	$value       = $request->get_param( 'value' );

	if ( empty( $option_name ) || empty( $autoload ) ) {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'Invalid option name or autoload value.', 'performance-lab' ),
			),
			400
		);
	}

	$result = wp_set_option_autoload( $option_name, $autoload );

	if ( $result ) {
		// Update modified options list.
		$modified_options = get_option( 'perflab_aao_modified_options', array() );
		if ( 'yes' === $autoload && isset( $modified_options[ $option_name ] ) ) {
			unset( $modified_options[ $option_name ] );
		} else {
			$modified_options[ $option_name ] = $value;
		}
		update_option( 'perflab_aao_modified_options', $modified_options );

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => sprintf(
					/* translators: 1: Autoload value, 2: Option name */
					__( 'Autoload %1$s for option %2$s.', 'performance-lab' ),
					$autoload,
					$option_name
				),
			),
			200
		);
	} else {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => __( 'Failed to update autoload status.', 'performance-lab' ),
			),
			500
		);
	}
}

/**
 * Enqueue JavaScript for disabling autoload.
 *
 * @since n.e.x.t
 *
 * @param string $hook_suffix The current admin page.
 */
function perflab_aao_enqueue_script( $hook_suffix ) {
	if ( 'site-health.php' !== $hook_suffix ) {
		return;
	}
	wp_enqueue_script( 'perflab-aao-update-autoload', plugin_dir_url( __FILE__ ) . 'update-autoload.js', array( 'jquery' ), 'n.e.x.t', true );

	wp_localize_script(
		'perflab-aao-update-autoload',
		'perflabAutoloadSettings',
		array(
			'root' => sanitize_url( get_rest_url() ),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'perflab_aao_enqueue_script' );
