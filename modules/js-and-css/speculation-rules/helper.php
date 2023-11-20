<?php
/**
 * Helper functions used for Speculation Rules.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Returns the speculation rules.
 *
 * Plugins with features that rely on frontend URLs to exclude from prefetching or prerendering should use the
 * {@see 'plsr_speculation_rules_href_exclude_paths'} filter to ensure those URL patterns are excluded.
 *
 * @since n.e.x.t
 *
 * @return array Associative array of speculation rules by type.
 */
function plsr_get_speculation_rules() {
	$base_href_exclude_paths = array(
		'/wp-login.php',
		'/wp-admin/*',
	);
	$href_exclude_paths      = $base_href_exclude_paths;

	/**
	 * Filters the paths for which speculative prerendering should be disabled.
	 *
	 * All paths should start in a forward slash, relative to the root document. The `*` can be used as a wildcard.
	 * By default, the array includes `/wp-login.php` and `/wp-admin/*`.
	 *
	 * @since n.e.x.t
	 *
	 * @param array $href_exclude_paths Paths to disable speculative prerendering for.
	 */
	$href_exclude_paths = (array) apply_filters( 'plsr_speculation_rules_href_exclude_paths', $href_exclude_paths );

	// Ensure that there are no duplicates and that the base paths cannot be removed.
	$href_exclude_paths = array_unique(
		array_map(
			static function ( $exclude_path ) {
				if ( ! str_starts_with( $exclude_path, '/' ) ) {
					$exclude_path = '/' . $exclude_path;
				}

				/*
				 * TODO: Remove this eventually as it's no longer needed in Chrome 121+.
				 * See:
				 * * https://github.com/whatwg/urlpattern/issues/179
				 * * https://chromium-review.googlesource.com/c/chromium/src/+/4975595
				 */
				return $exclude_path . '\\?*#*';
			},
			array_merge(
				$base_href_exclude_paths,
				$href_exclude_paths
			)
		)
	);

	$prerender_rules = array(
		array(
			'source'    => 'document',
			'where'     => array(
				'and' => array(
					// Prerender any URLs within the same site.
					array(
						'href_matches' => '/*\\?*',
						'relative_to'  => 'document',
					),
					// Except for WP login and admin URLs.
					array(
						'not' => array(
							'href_matches' => $href_exclude_paths,
						),
					),
					// And except for any links marked with a class to not prerender.
					array(
						'not' => array(
							'selector_matches' => '.no-prerender',
						),
					),
				),
			),
			'eagerness' => 'moderate',
		),
	);

	return array( 'prerender' => $prerender_rules );
}
