<?php
/**
 * Main integration file.
 *
 * @package performance-lab
 * @since 1.8.0
 */

// Require the constants file.
require_once dirname( dirname( __DIR__ ) ) . '/constants.php';

// Bail early if DATABASE_TYPE is not defined as sqlite.
if ( ! defined( 'DATABASE_TYPE' ) || 'sqlite' !== DATABASE_TYPE ) {
	return;
}

if ( ! extension_loaded( 'pdo' ) ) {
	wp_die(
		new WP_Error(
			'pdo_not_loaded',
			sprintf(
				'<h1>%1$s</h1><p>%2$s</p>',
				'PHP PDO Extension is not loaded',
				'Your PHP installation appears to be missing the PDO extension which is required for this version of WordPress and the type of database you have specified.'
			)
		),
		'PHP PDO Extension is not loaded.'
	);
}

if ( ! extension_loaded( 'pdo_sqlite' ) ) {
	wp_die(
		new WP_Error(
			'pdo_driver_not_loaded',
			sprintf(
				'<h1>%1$s</h1><p>%2$s</p>',
				'PDO Driver for SQLite is missing',
				'Your PHP installation appears not to have the right PDO drivers loaded. These are required for this version of WordPress and the type of database you have specified.'
			)
		),
		'PDO Driver for SQLite is missing.'
	);
}

require_once __DIR__ . '/class-perflab-sqlite-pdo-user-defined-functions.php';
require_once __DIR__ . '/class-perflab-sqlite-pdo-engine.php';
require_once __DIR__ . '/class-perflab-sqlite-object-array.php';
require_once __DIR__ . '/class-perflab-sqlite-db.php';
require_once __DIR__ . '/class-perflab-sqlite-pdo-driver.php';
require_once __DIR__ . '/class-perflab-sqlite-create-query.php';
require_once __DIR__ . '/class-perflab-sqlite-alter-query.php';
require_once __DIR__ . '/install-functions.php';

$GLOBALS['wpdb'] = new Perflab_SQLite_DB();
