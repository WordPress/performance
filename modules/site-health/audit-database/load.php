<?php
/** Module Name: Database Health Check
 * Description: Adds MariaDB / MySQL performance health checks and diagnostics.
 * Experimental: Yes
 *
 * @package performance-lab
 * @since 1.3.0
 */

/**
 * Adds Database Performance tests to site health.
 *
 * @param array $tests Site Health Tests.
 *
 * @return array
 * @since 1.3.0
 */
function perflab_ad_add_database_performance_tests( $tests ) {

	require_once __DIR__ . '/class-perflabdbutilities.php';
	require_once __DIR__ . '/class-perflabdbmetrics.php';
	require_once __DIR__ . '/class-perflabdbtests.php';

	wp_enqueue_style( 'perflabdblists', plugin_dir_url( __FILE__ ) . 'assets/perflabdblists.css' );

	$pdm = new PerflabDbMetrics();
	$pdp = new PerflabDbTests( $pdm );

	return $pdp->add_all_database_performance_checks( $tests );
}

add_filter( 'site_status_tests', 'perflab_ad_add_database_performance_tests' );
