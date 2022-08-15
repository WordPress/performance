<?php
/**
 * Module Name: Enqueued Assets Health Check
 * Description: Adds a CSS and JS resource check in Site Health status.
 * Experimental: Yes
 *
 * @package performance-lab
 * @since 1.0.0
 */

/**
 * Require helper functions.
 */
require_once __DIR__ . '/helper.php';

/**
 * Audit enqueued and printed scripts in is_front_page(). Ignore /wp-includes scripts.
 *
 * It will save information in a transient for 12 hours.
 *
 * @since 1.0.0
 */
function perflab_aea_audit_enqueued_scripts() {
	if ( ! is_admin() && is_front_page() && current_user_can( 'view_site_health_checks' ) && false === get_transient( 'aea_enqueued_front_page_scripts' ) ) {
		global $wp_scripts;
		$enqueued_scripts = array();

		foreach ( $wp_scripts->done as $handle ) {
			$script = $wp_scripts->registered[ $handle ];

			if ( ! $script->src || false !== strpos( $script->src, 'wp-includes' ) ) {
				continue;
			}

			// Add any extra data (inlined) that was passed with the script.
			$inline_size = 0;
			if ( ! empty( $script->extra ) && ! empty( $script->extra['after'] ) ) {
				foreach ( $script->extra['after'] as $extra ) {
					$inline_size += ( is_string( $extra ) ) ? mb_strlen( $extra, '8bit' ) : 0;
				}
			}

			$path = perflab_aea_get_path_from_resource_url( $script->src );
			if ( ! $path ) {
				continue;
			}

			$enqueued_scripts[] = array(
				'src'  => $script->src,
				'size' => wp_filesize( $path ) + $inline_size,
			);

		}
		set_transient( 'aea_enqueued_front_page_scripts', $enqueued_scripts, 12 * HOUR_IN_SECONDS );
	}
}
add_action( 'wp_footer', 'perflab_aea_audit_enqueued_scripts', PHP_INT_MAX );

/**
 * Audit enqueued and printed styles in the frontend. Ignore /wp-includes styles.
 *
 * It will save information in a transient for 12 hours.
 *
 * @since 1.0.0
 */
function perflab_aea_audit_enqueued_styles() {
	if ( ! is_admin() && is_front_page() && current_user_can( 'view_site_health_checks' ) && false === get_transient( 'aea_enqueued_front_page_styles' ) ) {
		global $wp_styles;
		$enqueued_styles = array();
		foreach ( $wp_styles->done as $handle ) {
			$style = $wp_styles->registered[ $handle ];

			if ( ! $style->src || false !== strpos( $style->src, 'wp-includes' ) ) {
				continue;
			}

			// Check if we already have the style's path ( part of a refactor for block styles from 5.9 ).
			if ( ! empty( $style->extra ) && ! empty( $style->extra['path'] ) ) {
				$path = $style->extra['path'];
			} else { // Fallback to getting the path from the style's src.
				$path = perflab_aea_get_path_from_resource_url( $style->src );
				if ( ! $path ) {
					continue;
				}
			}

			// Add any extra data (inlined) that was passed with the style.
			$inline_size = 0;
			if ( ! empty( $style->extra ) && ! empty( $style->extra['after'] ) ) {
				foreach ( $style->extra['after'] as $extra ) {
					$inline_size += ( is_string( $extra ) ) ? mb_strlen( $extra, '8bit' ) : 0;
				}
			}

			$enqueued_styles[] = array(
				'src'  => $style->src,
				'size' => wp_filesize( $path ) + $inline_size,
			);
		}
		set_transient( 'aea_enqueued_front_page_styles', $enqueued_styles, 12 * HOUR_IN_SECONDS );
	}
}
add_action( 'wp_footer', 'perflab_aea_audit_enqueued_styles', PHP_INT_MAX );

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
		'label' => __( 'JS assets', 'performance-lab' ),
		'test'  => 'perflab_aea_enqueued_js_assets_test',
	);
	$tests['direct']['enqueued_css_assets'] = array(
		'label' => __( 'CSS assets', 'performance-lab' ),
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
		'label'       => __( 'Enqueued scripts', 'performance-lab' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => __( 'Performance', 'performance-lab' ),
			'color' => 'blue',
		),
		'description' => sprintf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: Number of enqueued styles. 2.Styles size. */
					_n(
						'The amount of %1$s enqueued script (size: %2$s) is acceptable.',
						'The amount of %1$s enqueued scripts (size: %2$s) is acceptable.',
						$enqueued_scripts,
						'performance-lab'
					),
					$enqueued_scripts,
					size_format( perflab_aea_get_total_size_bytes_enqueued_scripts() )
				)
			)
		),
		'actions'     => '',
		'test'        => 'enqueued_js_assets',
	);

	/**
	 * Filters number of enqueued scripts to trigger warning.
	 *
	 * @since 1.0.0
	 *
	 * @param int $scripts_treshold Scripts threshold number. Default 30.
	 */
	$scripts_treshold = apply_filters( 'perflab_aea_enqueued_scripts_threshold', 30 );

	/**
	 * Filters size of enqueued scripts to trigger warning.
	 *
	 * @since 1.0.0
	 *
	 * @param int $scripts_size_treshold Enqueued Scripts size (in bytes) threshold. Default 300000.
	 */
	$scripts_size_treshold = apply_filters( 'perflab_aea_enqueued_scripts_byte_size_threshold', 300000 );

	if ( $enqueued_scripts > $scripts_treshold || perflab_aea_get_total_size_bytes_enqueued_scripts() > $scripts_size_treshold ) {
		$result['status'] = 'recommended';

		$result['description'] = sprintf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: Number of enqueued styles. 2.Styles size. */
					_n(
						'Your website enqueues %1$s script (size: %2$s). Try to reduce the number or to concatenate them.',
						'Your website enqueues %1$s scripts (size: %2$s). Try to reduce the number or to concatenate them.',
						$enqueued_scripts,
						'performance-lab'
					),
					$enqueued_scripts,
					size_format( perflab_aea_get_total_size_bytes_enqueued_scripts() )
				)
			)
		);

		$result['actions'] = sprintf(
			/* translators: 1: HelpHub URL. 2: Link description. 3.URL to clean cache. 4. Clean Cache text. */
			'<p><a target="_blank" href="%1$s">%2$s</a></p><p><a href="%3$s">%4$s</a></p>',
			esc_url( __( 'https://wordpress.org/support/article/optimization/', 'performance-lab' ) ),
			__( 'More info about performance optimization', 'performance-lab' ),
			esc_url( add_query_arg( 'action', 'clean_aea_audit', wp_nonce_url( admin_url( 'site-health.php' ), 'clean_aea_audit' ) ) ),
			__( 'Clean Test Cache', 'performance-lab' )
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
		'label'       => __( 'Enqueued styles', 'performance-lab' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => __( 'Performance', 'performance-lab' ),
			'color' => 'blue',
		),
		'description' => sprintf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: Number of enqueued styles. 2.Styles size. */
					_n(
						'The amount of %1$s enqueued style (size: %2$s) is acceptable.',
						'The amount of %1$s enqueued styles (size: %2$s) is acceptable.',
						$enqueued_styles,
						'performance-lab'
					),
					$enqueued_styles,
					size_format( perflab_aea_get_total_size_bytes_enqueued_styles() )
				)
			)
		),
		'actions'     => '',
		'test'        => 'enqueued_css_assets',
	);

	/**
	 * Filters number of enqueued styles to trigger warning.
	 *
	 * @since 1.0.0
	 *
	 * @param int $styles_threshold Styles threshold number. Default 10.
	 */
	$styles_threshold = apply_filters( 'perflab_aea_enqueued_styles_threshold', 10 );

	/**
	 * Filters size of enqueued styles to trigger warning.
	 *
	 * @since 1.0.0
	 *
	 * @param int $styles_size_threshold Enqueued styles size (in bytes) threshold. Default 100000.
	 */
	$styles_size_threshold = apply_filters( 'perflab_aea_enqueued_styles_byte_size_threshold', 100000 );
	if ( $enqueued_styles > $styles_threshold || perflab_aea_get_total_size_bytes_enqueued_styles() > $styles_size_threshold ) {
		$result['status'] = 'recommended';

		$result['description'] = sprintf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: Number of enqueued styles. 2.Styles size. */
					_n(
						'Your website enqueues %1$s style (size: %2$s). Try to reduce the number or to concatenate them.',
						'Your website enqueues %1$s styles (size: %2$s). Try to reduce the number or to concatenate them.',
						$enqueued_styles,
						'performance-lab'
					),
					$enqueued_styles,
					size_format( perflab_aea_get_total_size_bytes_enqueued_styles() )
				)
			)
		);

		$result['actions'] = sprintf(
			/* translators: 1: HelpHub URL. 2: Link description. 3.URL to clean cache. 4. Clean Cache text. */
			'<p><a target="_blank" href="%1$s">%2$s</a></p><p><a href="%3$s">%4$s</a></p>',
			esc_url( __( 'https://wordpress.org/support/article/optimization/', 'performance-lab' ) ),
			__( 'More info about performance optimization', 'performance-lab' ),
			esc_url( add_query_arg( 'action', 'clean_aea_audit', wp_nonce_url( admin_url( 'site-health.php' ), 'clean_aea_audit' ) ) ),
			__( 'Clean Test Cache', 'performance-lab' )
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

