<?php
/**
 * Module Name: Audit Enqueued Assets
 * Description: Adds a CSS and JS resource checker in Site Health checks.
 * Experimental: No
 *
 * @package performance-lab
 * @since 1.0.0
 */

/**
 * Require helper functions.
 */
require_once __DIR__ . '/helper.php';

/**
 * Audit enqueued scripts in is_front_page(). Ignore /wp-includes scripts.
 *
 * It will save information in a transient for 12 hours.
 *
 * @since 1.0.0
 */
function perflab_aea_audit_enqueued_scripts() {
	if ( ! is_admin() && is_front_page() && current_user_can( 'view_site_health_checks' ) && false === get_transient( 'aea_enqueued_front_page_scripts' ) ) {
		global $wp_scripts;
		$enqueued_scripts = array();

		foreach ( $wp_scripts->queue as $handle ) {
			$src = $wp_scripts->registered[ $handle ]->src;
			if ( $src && ! strpos( $src, 'wp-includes' ) ) {
				$enqueued_scripts[] = array(
					'src'  => $src,
					'size' => perflab_aea_get_resource_file_size( perflab_aea_get_path_from_resource_url( $src ) ),
				);
			}
		}
		$expiration = apply_filters( 'perflab_aea_enqueued_front_page_scripts_expiration_in_seconds', 12 * HOUR_IN_SECONDS );
		set_transient( 'aea_enqueued_front_page_scripts', $enqueued_scripts, $expiration );
	}
}
add_action( 'wp_print_scripts', 'perflab_aea_audit_enqueued_scripts' );

/**
 * Audit enqueued styles in the frontend. Ignore /wp-includes styles.
 *
 * It will save information in a transient for 12 hours.
 *
 * @since 1.0.0
 */
function perflab_aea_audit_enqueued_styles() {
	if ( ! is_admin() && is_front_page() && current_user_can( 'view_site_health_checks' ) && false === get_transient( 'aea_enqueued_front_page_styles' ) ) {
		global $wp_styles;
		$enqueued_styles = array();
		foreach ( $wp_styles->queue as $handle ) {
			$src = $wp_styles->registered[ $handle ]->src;
			if ( $src && ! strpos( $src, 'wp-includes' ) ) {
				$enqueued_styles[] = array(
					'src'  => $src,
					'size' => perflab_aea_get_resource_file_size( perflab_aea_get_path_from_resource_url( $src ) ),
				);
			}
		}
		$expiration = apply_filters( 'perflab_aea_enqueued_front_page_styles_expiration_in_seconds', 12 * HOUR_IN_SECONDS );
		set_transient( 'aea_enqueued_front_page_styles', $enqueued_styles, $expiration );
	}
}
add_action( 'wp_print_styles', 'perflab_aea_audit_enqueued_styles' );

/**
 * Adds tests to site health.
 *
 * @since 1.0.0
 *
 * @param array $tests Site Health Tests.
 * @return array
 */
function perflab_aea_add_enqueued_assets_test( $tests ) {
	$tests['direct']['enqueued_js_assets']  = array(
		'label' => esc_html__( 'JS assets', 'performance-lab' ),
		'test'  => 'perflab_aea_enqueued_js_assets_test',
	);
	$tests['direct']['enqueued_css_assets'] = array(
		'label' => esc_html__( 'CSS assets', 'performance-lab' ),
		'test'  => 'perflab_aea_enqueued_css_assets_test',
	);

	return $tests;
}
add_filter( 'site_status_tests', 'perflab_aea_add_enqueued_assets_test' );

/**
 * Callback for enqueued_js_assets test.
 *
 * @since 1.0.0
 *
 * @return array
 */
function perflab_aea_enqueued_js_assets_test() {
	/**
	 * If the test didn't run yet, deactivate.
	 */
	$enqueued_scripts = perflab_aea_get_total_enqueued_scripts();
	if ( false === $enqueued_scripts ) {
		return array();
	}

	$result = array(
		'label'       => esc_html__( 'Enqueued scripts', 'performance-lab' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => esc_html__( 'Performance', 'performance-lab' ),
			'color' => 'blue',
		),
		'description' => sprintf(
		/* translators: 1: Number of enqueued scripts. 2: "script" word. 3.Scripts size. */
			'<p>' . esc_html__( 'The amount of %1$s enqueued %2$s (size: %3$s) is acceptable.', 'performance-lab' ) . '</p>',
			$enqueued_scripts,
			_n( 'script', 'scripts', $enqueued_scripts, 'performance-lab' ),
			size_format( perflab_aea_get_total_size_bytes_enqueued_scripts() )
		),
		'actions'     => '',
		'test'        => 'enqueued_js_assets',
	);

	if ( $enqueued_scripts > 30 || perflab_aea_get_total_size_bytes_enqueued_scripts() > 300000 ) {
		$result['status']         = 'recommended';
		$result['badge']['color'] = 'orange';
		$result['description']    = sprintf(
		/* translators: 1: Number of enqueued scripts. 2: "script" word. 3.Scripts size. */
			'<p>' . esc_html__( 'Your website enqueues %1$s %2$s (size: %3$s). Try to reduce the number or to concatenate them.', 'performance-lab' ) . '</p>',
			$enqueued_scripts,
			_n( 'script', 'scripts', $enqueued_scripts, 'performance-lab' ),
			size_format( perflab_aea_get_total_size_bytes_enqueued_scripts() )
		);
		$result['actions'] .= sprintf(
			/* translators: 1: HelpHub URL. 2: Link description. 3.URL to clean cache. 4. Clean Cache text. */
			'<p><a target="_blank" href="%1$s">%2$s</a></p><p><a href="%3$s">%4$s</a></p>',
			esc_url( __( 'https://wordpress.org/support/article/optimization/', 'performance-lab' ) ),
			esc_html__( 'More info about performance optimization', 'performance-lab' ),
			esc_url( add_query_arg( 'action', 'clean_aea_audit', wp_nonce_url( admin_url( 'site-health.php' ), 'clean_aea_audit' ) ) ),
			esc_html__( 'Clean Test Cache', 'performance-lab' )
		);
	}

	return $result;
}

/**
 * Callback for enqueued_css_assets test.
 *
 * @since 1.0.0
 *
 * @return array
 */
function perflab_aea_enqueued_css_assets_test() {
	/**
	 * If the test didn't run yet, deactivate.
	 */
	$enqueued_styles = perflab_aea_get_total_enqueued_styles();
	if ( false === $enqueued_styles ) {
		return array();
	}
	$result = array(
		'label'       => esc_html__( 'Enqueued styles', 'performance-lab' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => esc_html__( 'Performance', 'performance-lab' ),
			'color' => 'blue',
		),
		'description' => sprintf(
		/* translators: 1: Number of enqueued styles. 2: "styles" word. 3.Styles size. */
			'<p>' . esc_html__( 'The amount of %1$s enqueued %2$s (size: %3$s) is acceptable.', 'performance-lab' ) . '</p>',
			$enqueued_styles,
			_n( 'style', 'styles', $enqueued_styles, 'performance-lab' ),
			size_format( perflab_aea_get_total_size_bytes_enqueued_styles() )
		),
		'actions'     => '',
		'test'        => 'enqueued_css_assets',
	);

	if ( $enqueued_styles > 10 || perflab_aea_get_total_size_bytes_enqueued_styles() > 100000 ) {
		$result['status']         = 'recommended';
		$result['badge']['color'] = 'orange';
		$result['description']    = sprintf(
		/* translators: 1: Number of enqueued styles. 2: "style" word. 3.Styles size. */
			'<p>' . esc_html__( 'Your website enqueues %1$s %2$s (size: %3$s). Try to reduce the number or to concatenate them.', 'performance-lab' ) . '</p>',
			$enqueued_styles,
			_n( 'style', 'styles', $enqueued_styles, 'performance-lab' ),
			size_format( perflab_aea_get_total_size_bytes_enqueued_styles() )
		);

		$result['actions'] .= sprintf(
			/* translators: 1: HelpHub URL. 2: Link description. 3.URL to clean cache. 4. Clean Cache text. */
			'<p><a target="_blank" href="%1$s">%2$s</a></p><p><a href="%3$s">%4$s</a></p>',
			esc_url( __( 'https://wordpress.org/support/article/optimization/', 'performance-lab' ) ),
			esc_html__( 'More info about performance optimization', 'performance-lab' ),
			esc_url( add_query_arg( 'action', 'clean_aea_audit', wp_nonce_url( admin_url( 'site-health.php' ), 'clean_aea_audit' ) ) ),
			esc_html__( 'Clean Test Cache', 'performance-lab' )
		);
	}

	return $result;
}

/**
 * Invalidate both transients/cache on user clean_aea_audit action.
 * Redirects to site-health.php screen adter clean up.
 *
 * @since 1.0.0
 */
function perflab_aea_clean_aea_audit_action() {
	if ( isset( $_GET['action'] ) && 'clean_aea_audit' === $_GET['action'] && current_user_can( 'view_site_health_checks' ) ) {
		check_admin_referer( 'clean_aea_audit' );
		perflab_aea_invalidate_cache_transients();
		wp_safe_redirect( remove_query_arg( array( 'action', '_wpnonce' ), wp_get_referer() ) );
	}
}
add_action( 'admin_init', 'perflab_aea_clean_aea_audit_action' );

/**
 * Invalidate both transients/cache.
 *
 * @since 1.0.0
 */
function perflab_aea_invalidate_cache_transients() {
	delete_transient( 'aea_enqueued_front_page_scripts' );
	delete_transient( 'aea_enqueued_front_page_styles' );
}
add_action( 'switch_theme', 'perflab_aea_invalidate_cache_transients' );
add_action( 'activated_plugin', 'perflab_aea_invalidate_cache_transients' );
add_action( 'deactivated_plugin', 'perflab_aea_invalidate_cache_transients' );

