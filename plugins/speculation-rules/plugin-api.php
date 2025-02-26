<?php
/**
 * Plugin API for Speculative Loading.
 *
 * @package speculation-rules
 * @since 1.0.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Returns the speculation rules.
 *
 * Plugins with features that rely on frontend URLs to exclude from prefetching or prerendering should use the
 * {@see 'plsr_speculation_rules_href_exclude_paths'} filter to ensure those URL patterns are excluded.
 *
 * @since 1.0.0
 *
 * @return non-empty-array<string, array<int, array<string, mixed>>> Associative array of speculation rules by type.
 */
function plsr_get_speculation_rules(): array {
	$option    = plsr_get_stored_setting_value();
	$mode      = $option['mode'];
	$eagerness = $option['eagerness'];

	$prefixer = new PLSR_URL_Pattern_Prefixer();

	$base_href_exclude_paths = array(
		$prefixer->prefix_path_pattern( '/wp-*.php', 'site' ),
		$prefixer->prefix_path_pattern( '/wp-admin/*', 'site' ),
		$prefixer->prefix_path_pattern( '/*', 'uploads' ),
		$prefixer->prefix_path_pattern( '/*', 'content' ),
		$prefixer->prefix_path_pattern( '/*', 'plugins' ),
		$prefixer->prefix_path_pattern( '/*', 'template' ),
		$prefixer->prefix_path_pattern( '/*', 'stylesheet' ),
	);

	/*
	 * If pretty permalinks are enabled, exclude any URLs with query parameters.
	 * Otherwise, exclude specifically the URLs with a `_wpnonce` query parameter.
	 */
	if ( (bool) get_option( 'permalink_structure' ) ) {
		$base_href_exclude_paths[] = $prefixer->prefix_path_pattern( '/*\\?(.+)', 'home' );
	} else {
		$base_href_exclude_paths[] = $prefixer->prefix_path_pattern( '/*\\?*(^|&)_wpnonce=*', 'home' );
	}

	/**
	 * Filters the paths for which speculative prerendering should be disabled.
	 *
	 * All paths should start in a forward slash, relative to the root document. The `*` can be used as a wildcard.
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
							'selector_matches' => 'a[rel~="nofollow"]',
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

/**
 * Prints the speculation rules.
 *
 * For browsers that do not support speculation rules yet, the `script[type="speculationrules"]` tag will be ignored.
 *
 * @since 1.0.0
 */
function plsr_print_speculation_rules(): void {
	// Skip speculative loading for logged-in users.
	if ( is_user_logged_in() ) {
		return;
	}

	// Skip speculative loading for sites without pretty permalinks, unless explicitly enabled.
	if ( ! (bool) get_option( 'permalink_structure' ) ) {
		/**
		 * Filters whether speculative loading should be enabled even though the site does not use pretty permalinks.
		 *
		 * Since query parameters are commonly used by plugins for dynamic behavior that can change state, ideally any
		 * such URLs are excluded from speculative loading. If the site does not use pretty permalinks though, they are
		 * impossible to recognize. Therefore speculative loading is disabled by default for those sites.
		 *
		 * For site owners of sites without pretty permalinks that are certain their site is not using such a pattern,
		 * this filter can be used to still enable speculative loading at their own risk.
		 *
		 * @since 1.4.0
		 *
		 * @param bool $enabled Whether speculative loading is enabled even without pretty permalinks.
		 */
		$enabled = (bool) apply_filters( 'plsr_enabled_without_pretty_permalinks', false );

		if ( ! $enabled ) {
			return;
		}
	}

	wp_print_inline_script_tag(
		(string) wp_json_encode( plsr_get_speculation_rules() ),
		array( 'type' => 'speculationrules' )
	);
}
