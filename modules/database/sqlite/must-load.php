<?php
/**
 * Functions that should always be loaded, regardless of whether the module is active or not.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

// Require the constants file.
require_once __DIR__ . '/constants.php';

/**
 * Trigger actions when the module gets activated or deactivated.
 *
 * This action needs to be in the must-load.php file,
 * because the load.php file only gets loaded when the module is already active.
 *
 * @since n.e.x.t
 *
 * @param string $option    The option name.
 * @param mixed  $old_value Old value of the option.
 * @param mixed  $value     New value of the option.
 *
 * @return void
 */
function perflab_sqlite_module_update_option( $option, $old_value, $value ) {
	if ( PERFLAB_MODULES_SETTING !== $option ) {
		return;
	}

	// Figure out we're activating or deactivating the module.
	$sqlite_was_active   = isset( $old_value['database/sqlite'] ) && ! empty( $old_value['database/sqlite']['enabled'] );
	$sqlite_is_active    = isset( $value['database/sqlite'] ) && ! empty( $value['database/sqlite']['enabled'] );
	$activating_sqlite   = $sqlite_is_active && ! $sqlite_was_active;
	$deactivating_sqlite = ! $sqlite_is_active && $sqlite_was_active;

	// Load the load.php file if functions don't exist.
	if ( ! function_exists( 'perflab_sqlite_module_copy_db_file' ) ) {
		require_once __DIR__ . '/load.php';
	}

	// If we're activating the module, copy the db.php file.
	if ( $activating_sqlite ) {
		perflab_sqlite_module_copy_db_file();
	}

	// If we are deactivating the module, delete the db.php file.
	if ( $deactivating_sqlite ) {
		perflab_sqlite_module_delete_db_file();

		// Run an action on `shutdown`, to deactivate the option in the MySQL database.
		add_action( 'shutdown', 'perflab_sqlite_module_deactivate_module_in_mysql' );
	}
	return $value;
}
add_action( 'update_option', 'perflab_sqlite_module_update_option', 10, 3 );
