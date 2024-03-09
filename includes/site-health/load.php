<?php
/**
 * Site Health tests loader.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! has_filter( 'user_has_cap', 'wp_maybe_grant_site_health_caps' ) ) {
	return;
}

// Site health tests to load.
$site_health_tests = array(
	'audit-autoloaded-options',
	'audit-enqueued-assets',
	'webp-support'
);

foreach ( $site_health_tests as $test ) {
	require_once __DIR__ . '/' . $test . '/helper.php';
	require_once __DIR__ . '/' . $test . '/hooks.php';
}
