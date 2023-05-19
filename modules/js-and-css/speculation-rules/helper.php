<?php
/**
 * Helper functions used for Speculation Rules.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Prints the speculation rules in a cross-browser compatible way.
 *
 * For browsers that do not support speculation rules yet, the rules will not be loaded.
 *
 * @since n.e.x.t
 *
 * @return array Associative array of speculation rules by type.
 */
function plsr_get_speculation_rules() {
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
							'href_matches' => array(
								'/wp-login.php\\?*#*',
								'/wp-admin/*\\?*#*',
							),
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
