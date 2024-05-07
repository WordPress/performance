<?php
/**
 * Helper functions used for Speculative Loading.
 *
 * @package speculation-rules
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the speculation rules.
 *
 * Plugins with features that rely on frontend URLs to exclude from prefetching or prerendering should use the
 * {@see 'plsr_speculation_rules_href_exclude_paths'} filter to ensure those URL patterns are excluded.
 *
 * @since 1.0.0
 *
 * @return array<string, mixed> Associative array of speculation rules by type.
 */
function plsr_get_speculation_rules(): array {
	$option = get_option( 'plsr_speculation_rules' );

	/*
	 * This logic is only relevant for edge-cases where the setting may not be registered,
	 * a.k.a. defensive coding.
	 */
	if ( ! $option || ! is_array( $option ) ) {
		$option = plsr_get_setting_default();
	} else {
		$option = array_merge( plsr_get_setting_default(), $option );
	}

	$mode      = $option['mode'];
	$eagerness = $option['eagerness'];

	$prefixer = new PLSR_URL_Pattern_Prefixer();

	$base_href_exclude_paths = array(
		$prefixer->prefix_path_pattern( '/wp-login.php', 'site' ),
		$prefixer->prefix_path_pattern( '/wp-admin/*', 'site' ),
		$prefixer->prefix_path_pattern( '/*\\?*(^|&)_wpnonce=*', 'home' ),
		$prefixer->prefix_path_pattern( '/*', 'uploads' ),
		$prefixer->prefix_path_pattern( '/*', 'content' ),
		$prefixer->prefix_path_pattern( '/*', 'plugins' ),
		$prefixer->prefix_path_pattern( '/*', 'template' ),
		$prefixer->prefix_path_pattern( '/*', 'stylesheet' ),
	);

	/**
	 * Filters the paths for which speculative prerendering should be disabled.
	 *
	 * All paths should start in a forward slash, relative to the root document. The `*` can be used as a wildcard.
	 * By default, the array includes `/wp-login.php` and `/wp-admin/*`.
	 *
	 * If the WordPress site is in a subdirectory, the exclude paths will automatically be prefixed as necessary.
	 *
	 * @since 1.0.0
	 * @since 1.1.0 The $mode parameter was added.
	 *
	 * @param string[] $href_exclude_paths Additional paths to disable speculative prerendering for. The base exclude paths,
	 *                                     such as for wp-admin, cannot be removed.
	 * @param string   $mode               Mode used to apply speculative prerendering. Either 'prefetch' or 'prerender'.
	 */
	$href_exclude_paths = (array) apply_filters( 'plsr_speculation_rules_href_exclude_paths', array(), $mode );

	// Ensure that:
	// 1. There are no duplicates.
	// 2. The base paths cannot be removed.
	// 3. The array has sequential keys (i.e. array_is_list()).
	$href_exclude_paths = array_values(
		array_unique(
			array_merge(
				$base_href_exclude_paths,
				array_map(
					static function ( string $href_exclude_path ) use ( $prefixer ): string {
						return $prefixer->prefix_path_pattern( $href_exclude_path );
					},
					$href_exclude_paths
				)
			)
		)
	);

	$rules = array(
		array(
			'source'    => 'document',
			'where'     => array(
				'and' => array(
					// Include any URLs within the same site.
					array(
						'href_matches' => $prefixer->prefix_path_pattern( '/*' ),
					),
					// Except for WP login and admin URLs.
					array(
						'not' => array(
							'href_matches' => $href_exclude_paths,
						),
					),
					// Also exclude rel=nofollow links, as plugins like WooCommerce use that on their add-to-cart links.
					array(
						'not' => array(
							'selector_matches' => 'a[rel=nofollow]',
						),
					),
				),
			),
			'eagerness' => $eagerness,
		),
	);

	// Allow adding a class on any links to prevent prerendering.
	if ( 'prerender' === $mode ) {
		$rules[0]['where']['and'][] = array(
			'not' => array(
				'selector_matches' => '.no-prerender',
			),
		);
	}

	return array( $mode => $rules );
}
