<?php
/**
 * Hook callbacks used for Enqueued Assets Health Check.
 *
 * @package performance-lab
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

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
 * Invalidate both transients/cache on user clean_aea_audit action.
 * Redirects to site-health.php screen after clean up.
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
