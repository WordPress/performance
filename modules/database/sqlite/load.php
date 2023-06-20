<?php
/**
 * Module Name: SQLite Integration
 * Description: Use an SQLite database instead of MySQL.
 * Experimental: Yes
 *
 * @package performance-lab
 * @since 1.8.0
 */

// Define the version constant.
if ( defined( 'SQLITE_VERSION' ) ) {
	return;
}

define( 'SQLITE_VERSION', 'Performance Lab ' . PERFLAB_VERSION );

// Do not load the code if it is already loaded through another means.
if ( function_exists( 'perflab_sqlite_plugin_admin_notice' ) ) {
	return;
}

require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/site-health.php';
