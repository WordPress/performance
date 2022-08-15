<?php
/** Module Name: Database Index Check
 * Description: Checks for, and guides the creation of, high-performance keys on tables in your MariaDB / MySQL database. This health check is most useful to owners of sites with many users, posts, pages, or products.
 * Experimental: Yes
 *
 * @package performance-lab
 * @since 1.3.0
 */

/**
 * Adds Database Index tests to site health.
 *
 * @param array $tests Site Health Tests.
 *
 * @return array
 * @since 1.3.0
 */
function perflab_ad_add_database_index_tests( $tests ) {

	require_once __DIR__ . '/../audit-database/class-perflabdbutilities.php';
	require_once __DIR__ . '/../audit-database/class-perflabdbmetrics.php';
	require_once __DIR__ . '/class-perflabdbindexes.php';

	wp_enqueue_style( 'perflabdbclippables', plugin_dir_url( __FILE__ ) . 'assets/perflabdbclippables.css' );
	wp_enqueue_script( 'perflabclipboard', includes_url() . 'js/clipboard.js' );
	wp_enqueue_script( 'perflabdbclip', plugin_dir_url( __FILE__ ) . 'assets/clip.js', array( 'jquery', 'perflabclipboard' ) );

	$pdm = new PerflabDbMetrics();
	$pdi = new PerflabDbIndexes( $pdm );

	return $pdi->add_all_database_index_checks( $tests );
}

add_filter( 'site_status_tests', 'perflab_ad_add_database_index_tests' );
