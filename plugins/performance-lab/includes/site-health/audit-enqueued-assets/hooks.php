<?php
/**
 * Hook callbacks used for Enqueued Assets Health Check.
 *
 * @package performance-lab
 * @since 2.1.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Audit blocking assets on the front page.
 *
 * @since n.e.x.t
 */
function perflab_aea_audit_blocking_assets(): void {
	if (
		! is_admin() ||
		! current_user_can( 'view_site_health_checks' ) ||
		false !== get_transient( 'aea_blocking_assets' )
	) {
		return;
	}

	$response = wp_remote_get(
		home_url( '/' ),
		array(
			'timeout' => 10,
			'headers' => array(
				'Accept'        => 'text/html',
				'Cache-Control' => 'no-cache',
			),
		)
	);

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return;
	}

	$html = wp_remote_retrieve_body( $response );
	if ( '' === $html ) {
		return;
	}

	$assets = array(
		'scripts' => array(),
		'styles'  => array(),
	);

	$processor = new WP_HTML_Tag_Processor( $html );

	while ( $processor->next_tag() ) {
		$tag = $processor->get_tag();

		if ( 'SCRIPT' === $tag ) {
			$src = $processor->get_attribute( 'src' );
			if ( ! is_string( $src ) ) {
				continue;
			}

			// Note that when the "type" attribute is absent or empty, the element is treated as a classic JavaScript script.
			$type = $processor->get_attribute( 'type' );

			// Skip external script with "async" or "defer" attributes.
			if ( null !== $processor->get_attribute( 'async' ) || null !== $processor->get_attribute( 'defer' ) ) {
				continue;
			}

			// Skip external script with a "type" attribute set to "module" as they are deferred by default.
			if ( 'module' === strtolower( (string) $type ) ) {
				continue;
			}

			// Skip external script with a "type" attribute that is not JavaScript.
			if (
				true !== $type &&
				null !== $type &&
				'' !== $type &&
				! (
					str_contains( (string) $type, 'javascript' ) ||
					str_contains( (string) $type, 'ecmascript' ) ||
					str_contains( (string) $type, 'jscript' ) ||
					str_contains( (string) $type, 'livescript' )
				)
			) {
				continue;
			}

			$size = perflab_aea_get_asset_content_length( $src );
			if ( null !== $size ) {
				$assets['scripts'][] = array(
					'src'  => $src,
					'size' => $size,
				);
			}
		} elseif ( 'LINK' === $tag ) {
			$rel = $processor->get_attribute( 'rel' );
			if ( 'stylesheet' !== strtolower( (string) $rel ) ) {
				continue;
			}

			$href = $processor->get_attribute( 'href' );
			if ( ! is_string( $href ) ) {
				continue;
			}

			$size = perflab_aea_get_asset_content_length( $href );
			if ( null !== $size ) {
				$assets['styles'][] = array(
					'src'  => $href,
					'size' => $size,
				);
			}
		}
	}

	set_transient( 'aea_blocking_assets', $assets, 12 * HOUR_IN_SECONDS );
}
add_action( 'admin_init', 'perflab_aea_audit_blocking_assets' );

/**
 * Adds tests to site health.
 *
 * @since 1.0.0
 *
 * @param array{direct: array<string, array{label: string, test: string}>} $tests Site Health Tests.
 * @return array{direct: array<string, array{label: string, test: string}>} Amended tests.
 */
function perflab_aea_add_enqueued_assets_test( array $tests ): array {
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
function perflab_aea_clean_aea_audit_action(): void {
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
function perflab_aea_invalidate_cache_transients(): void {
	delete_transient( 'aea_blocking_assets' );
	// Keeping legacy transients deletion for backward compatibility.
	delete_transient( 'aea_enqueued_front_page_scripts' );
	delete_transient( 'aea_enqueued_front_page_styles' );
}
add_action( 'switch_theme', 'perflab_aea_invalidate_cache_transients' );
add_action( 'activated_plugin', 'perflab_aea_invalidate_cache_transients' );
add_action( 'deactivated_plugin', 'perflab_aea_invalidate_cache_transients' );
