<?php
/**
 * Adds and filters data in the site-health screen.
 *
 * @package performance-lab
 * @since 1.8.0
 */

// Require the constants file.
require_once __DIR__ . '/constants.php';

/**
 * Filter debug data in site-health screen.
 *
 * When the plugin gets merged in wp-core, these should be merged in src/wp-admin/includes/class-wp-debug-data.php
 * See https://github.com/WordPress/wordpress-develop/pull/3220/files
 *
 * @since 1.8.0
 *
 * @param array $info The debug data.
 * @return array The filtered debug data.
 */
function perflab_sqlite_plugin_filter_debug_data( $info ) {
	$db_engine = defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ? 'sqlite' : 'mysql';

	$info['wp-constants']['fields']['DB_ENGINE'] = array(
		'label' => 'DB_ENGINE',
		'value' => ( defined( 'DB_ENGINE' ) ? DB_ENGINE : __( 'Undefined', 'performance-lab' ) ),
		'debug' => ( defined( 'DB_ENGINE' ) ? DB_ENGINE : 'undefined' ),
	);

	$info['wp-database']['fields']['db_engine'] = array(
		'label' => __( 'Database type', 'performance-lab' ),
		'value' => 'sqlite' === $db_engine ? 'SQLite' : 'MySQL/MariaDB',
	);

	if ( 'sqlite' === $db_engine ) {
		$info['wp-database']['fields']['database_version'] = array(
			'label' => __( 'SQLite version', 'performance-lab' ),
			'value' => class_exists( 'SQLite3' ) ? SQLite3::version()['versionString'] : null,
		);

		$info['wp-database']['fields']['database_file'] = array(
			'label'   => __( 'Database file', 'performance-lab' ),
			'value'   => FQDB,
			'private' => true,
		);

		$info['wp-database']['fields']['database_size'] = array(
			'label' => __( 'Database size', 'performance-lab' ),
			'value' => size_format( filesize( FQDB ) ),
		);

		unset( $info['wp-database']['fields']['extension'] );
		unset( $info['wp-database']['fields']['server_version'] );
		unset( $info['wp-database']['fields']['client_version'] );
		unset( $info['wp-database']['fields']['database_host'] );
		unset( $info['wp-database']['fields']['database_user'] );
		unset( $info['wp-database']['fields']['database_name'] );
		unset( $info['wp-database']['fields']['database_charset'] );
		unset( $info['wp-database']['fields']['database_collate'] );
		unset( $info['wp-database']['fields']['max_allowed_packet'] );
		unset( $info['wp-database']['fields']['max_connections'] );
	}

	return $info;
}
add_filter( 'debug_information', 'perflab_sqlite_plugin_filter_debug_data' ); // Filter debug data in site-health screen.
