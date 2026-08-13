<?php
/**
 * Helper functions for the Chrome DevTools third-party tools integration.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

declare( strict_types = 1 );

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Gets the capability required to access the Chrome DevTools third-party tools integration.
 *
 * @since n.e.x.t
 *
 * @return string Capability.
 */
function perflab_devtools_get_capability(): string {
	/**
	 * Filters the capability required to access the Chrome DevTools third-party tools integration.
	 *
	 * The exposed data includes database queries and environment details, so it is restricted
	 * to administrators by default.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $capability Capability. Default 'manage_options'.
	 */
	$capability = apply_filters( 'perflab_devtools_capability', 'manage_options' );
	if ( ! is_string( $capability ) || '' === $capability ) {
		$capability = 'manage_options';
	}
	return $capability;
}

/**
 * Gets the path to the DevTools discovery script, relative to the plugin directory.
 *
 * The unminified source is used when SCRIPT_DEBUG is enabled or when the minified copy has not
 * been built (e.g. in a development checkout).
 *
 * @since n.e.x.t
 *
 * @return string Script path relative to the plugin directory.
 */
function perflab_devtools_get_asset_path(): string {
	$src_path = 'includes/devtools/devtools-discovery.js';
	$min_path = 'includes/devtools/devtools-discovery.min.js';
	if (
		( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ||
		! file_exists( PERFLAB_PLUGIN_DIR_PATH . $min_path )
	) {
		return $src_path;
	}
	return $min_path;
}

/**
 * Enqueues the script module which registers the third-party tools with Chrome DevTools.
 *
 * The module is only served to users with the required capability, since the tools expose
 * internal state such as database queries.
 *
 * @since n.e.x.t
 */
function perflab_devtools_enqueue_script_module(): void {
	if ( ! current_user_can( perflab_devtools_get_capability() ) ) {
		return;
	}
	wp_enqueue_script_module(
		'perflab-devtools-discovery',
		plugins_url( perflab_devtools_get_asset_path(), PERFLAB_MAIN_FILE ),
		array(),
		PERFLAB_VERSION
	);
}

/**
 * Prints the data consumed by the DevTools discovery script module as a JSON script tag.
 *
 * This runs at the end of {@see 'wp_footer'} so that as much of the request as possible is
 * captured; database queries executed after this point are not included.
 *
 * @since n.e.x.t
 */
function perflab_devtools_print_data(): void {
	if ( ! current_user_can( perflab_devtools_get_capability() ) ) {
		return;
	}

	$data = array(
		'environment' => perflab_devtools_get_environment_info(),
		'dbQueries'   => perflab_devtools_get_database_queries(),
	);

	$json_flags = JSON_HEX_TAG | JSON_UNESCAPED_SLASHES;
	if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
		$json_flags |= JSON_PRETTY_PRINT;
	}
	wp_print_inline_script_tag(
		(string) wp_json_encode( $data, $json_flags ),
		array(
			'type' => 'application/json',
			'id'   => 'perflab-devtools-data',
		)
	);
}

/**
 * Gets information about the WordPress environment serving the current page.
 *
 * Only non-sensitive, high-level information is included, and the data is only ever exposed to
 * users with the capability returned by {@see perflab_devtools_get_capability()}.
 *
 * @since n.e.x.t
 *
 * @return array<string, mixed> Environment info.
 */
function perflab_devtools_get_environment_info(): array {
	$theme = wp_get_theme();

	return array(
		'wpVersion'                => get_bloginfo( 'version' ),
		'phpVersion'               => phpversion(),
		'theme'                    => array(
			'name'       => $theme->get( 'Name' ),
			'stylesheet' => $theme->get_stylesheet(),
			'version'    => $theme->get( 'Version' ),
		),
		'usingExternalObjectCache' => (bool) wp_using_ext_object_cache(),
		'wpDebug'                  => defined( 'WP_DEBUG' ) && WP_DEBUG,
		'saveQueries'              => defined( 'SAVEQUERIES' ) && SAVEQUERIES,
		'isMultisite'              => is_multisite(),
		'activePlugins'            => array_values( (array) get_option( 'active_plugins', array() ) ),
	);
}

/**
 * Gets the database queries executed during the current request.
 *
 * Requires the SAVEQUERIES constant to be defined as true, since otherwise WordPress does not
 * collect queries. Individual SQL strings are truncated to a reasonable length to keep the
 * payload size in check.
 *
 * @since n.e.x.t
 *
 * @return array{ count: int, totalTimeMs: float, queries: array<int, array{ sql: string, timeMs: float, caller: string }> }|null Queries data, or null if SAVEQUERIES is not enabled.
 */
function perflab_devtools_get_database_queries(): ?array {
	if ( ! defined( 'SAVEQUERIES' ) || ! SAVEQUERIES ) {
		return null;
	}

	// If no queries have been run yet, $wpdb->queries will be null, which is valid (0 queries).
	$saved_queries = $GLOBALS['wpdb']->queries ?? array();
	if ( ! is_array( $saved_queries ) ) {
		return null;
	}

	return perflab_devtools_map_database_queries( $saved_queries );
}

/**
 * Maps queries saved by wpdb via the SAVEQUERIES constant to the shape exposed to DevTools.
 *
 * @since n.e.x.t
 *
 * @param array<int, mixed> $saved_queries Queries as saved in wpdb::$queries, where each entry is expected to be
 *                                         an array containing the SQL, the time taken in seconds, and the caller.
 * @return array{ count: int, totalTimeMs: float, queries: array<int, array{ sql: string, timeMs: float, caller: string }> } Queries data.
 */
function perflab_devtools_map_database_queries( array $saved_queries ): array {
	$max_sql_length = 2000;
	$queries        = array();
	$total_time_ms  = 0.0;
	foreach ( $saved_queries as $saved_query ) {
		if ( ! is_array( $saved_query ) || ! isset( $saved_query[0], $saved_query[1], $saved_query[2] ) ) {
			continue;
		}
		$sql = trim( (string) $saved_query[0] );
		if ( strlen( $sql ) > $max_sql_length ) {
			$sql = substr( $sql, 0, $max_sql_length ) . '…';
		}
		$time_ms        = (float) $saved_query[1] * 1000.0;
		$total_time_ms += $time_ms;
		$queries[]      = array(
			'sql'    => $sql,
			'timeMs' => round( $time_ms, 3 ),
			'caller' => (string) $saved_query[2],
		);
	}

	return array(
		'count'       => count( $queries ),
		'totalTimeMs' => round( $total_time_ms, 3 ),
		'queries'     => $queries,
	);
}
