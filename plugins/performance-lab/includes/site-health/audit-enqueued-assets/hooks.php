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
 * Audit enqueued assets on the front page.
 *
 * @since n.e.x.t
 */
function perflab_aea_audit_enqueued_assets(): void {
	if (
		! is_admin() ||
		! current_user_can( 'view_site_health_checks' ) ||
		( false !== get_transient( 'aea_enqueued_front_page_scripts' ) && false !== get_transient( 'aea_enqueued_front_page_styles' ) )
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
	$processor->next_token();
	$processor->set_bookmark( 'start' );

	$current_script_handle = '';
	while ( $processor->next_tag( array( 'tag_name' => 'SCRIPT' ) ) ) {
		$src = $processor->get_attribute( 'src' );

		if ( null === $src && '' !== $current_script_handle ) {
			$inline_script_handle = $processor->get_attribute( 'id' );
			if ( is_string( $inline_script_handle ) && $inline_script_handle === $current_script_handle . '-after' ) {
				$script_size = mb_strlen( $processor->get_modifiable_text(), '8bit' );
				if ( false !== $script_size ) {
					foreach ( $assets['scripts'] as &$script ) {
						if ( $script['handle'] === $current_script_handle ) {
							$script['size'] += $script_size;
							break;
						}
					}
				}
				continue;
			}
		}

		if ( ! is_string( $src ) || false !== strpos( $src, 'wp-includes' ) ) {
			continue;
		}

		$path = perflab_aea_get_path_from_resource_url( $src );
		if ( '' === $path ) {
			continue;
		}

		$script_size           = filesize( $path );
		$current_script_handle = (string) $processor->get_attribute( 'id' );
		if ( false !== $script_size ) {
			$assets['scripts'][] = array(
				'src'    => $src,
				'size'   => $script_size,
				'handle' => $current_script_handle,
			);
		}
	}

	$processor->seek( 'start' );
	while ( $processor->next_tag( array( 'tag_name' => 'LINK' ) ) ) {
		$rel = $processor->get_attribute( 'rel' );
		if ( ! is_string( $rel ) || 'stylesheet' !== strtolower( $rel ) ) {
			continue;
		}

		$href = $processor->get_attribute( 'href' );
		if ( ! is_string( $href ) || false !== strpos( $href, 'wp-includes' ) ) {
			continue;
		}

		$path = perflab_aea_get_path_from_resource_url( $href );
		if ( '' === $path ) {
			continue;
		}

		$style_size = filesize( $path );
		if ( false !== $style_size ) {
			$assets['styles'][] = array(
				'src'    => $href,
				'size'   => $style_size,
				'handle' => (string) $processor->get_attribute( 'id' ),
			);
		}
	}

	$processor->seek( 'start' );
	while ( $processor->next_tag( array( 'tag_name' => 'STYLE' ) ) ) {
		$inline_script_handle = $processor->get_attribute( 'id' );
		if ( ! is_string( $inline_script_handle ) || '' === $inline_script_handle ) {
			continue;
		}
		foreach ( $assets['styles'] as &$style ) {
			if ( preg_replace( '/-css$/', '', $style['handle'] ) . '-inline-css' === $inline_script_handle ) {
				$style_size     = mb_strlen( $processor->get_modifiable_text(), '8bit' );
				$style['size'] += $style_size;
				break;
			}
		}
	}

	$processor->release_bookmark( 'start' );

	if ( 0 !== count( $assets['scripts'] ) ) {
		set_transient( 'aea_enqueued_front_page_scripts', $assets['scripts'], 12 * HOUR_IN_SECONDS );
	}
	if ( 0 !== count( $assets['styles'] ) ) {
		set_transient( 'aea_enqueued_front_page_styles', $assets['styles'], 12 * HOUR_IN_SECONDS );
	}
}
add_action( 'admin_init', 'perflab_aea_audit_enqueued_assets' );

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
	delete_transient( 'aea_enqueued_front_page_scripts' );
	delete_transient( 'aea_enqueued_front_page_styles' );
}
add_action( 'switch_theme', 'perflab_aea_invalidate_cache_transients' );
add_action( 'activated_plugin', 'perflab_aea_invalidate_cache_transients' );
add_action( 'deactivated_plugin', 'perflab_aea_invalidate_cache_transients' );
