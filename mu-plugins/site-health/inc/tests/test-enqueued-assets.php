<?php
/**
 * Site Health test for Enqueued Assets.
 *
 * @package performance-lab
 */

namespace Performance_Lab\Site_Health\Tests;

use WP_HTML_Tag_Processor;


/**
 * Site Health test to report enqueued CSS/JS asset stats.
 *
 * @return array Test result.
 */
function test_enqueued_assets(): array {

	global $wp_scripts, $wp_styles;

	$css_count = is_object( $wp_styles ) ? count( $wp_styles->queue ) : 0;
	$js_count  = is_object( $wp_scripts ) ? count( $wp_scripts->queue ) : 0;
	$total     = $css_count + $js_count;

	$max_assets = 15;

	$description = sprintf(
		'Your site is enqueuing %d CSS and JS files. Consider optimizing to improve performance.',
		$total
	);

	$status = ( $total > $max_assets ) ? 'recommended' : 'good';

	return array(
		'label'       => 'Enqueued Assets (CSS + JS)',
		'status'      => $status,
		'badge'       => array(
			'label' => 'Performance',
			'color' => 'blue',
		),
		'description' => $description,
		'actions'     => '',
		'test'        => 'enqueued_assets',
	);
}

add_filter(
	'site_status_tests',
	static function ( $tests ) {
		$tests['direct']['enqueued_assets'] = array(
			'label' => 'Enqueued Assets (CSS + JS)',
			'test'  => __NAMESPACE__ . '\\test_enqueued_assets',
		);
		return $tests;
	}
);
