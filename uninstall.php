<?php
/**
 * Plugin uninstaller logic.
 *
 * @package performance-lab
 * @since 1.2.0
 */

// If uninstall.php is not called by WordPress, bail.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'perflab_modules_settings' );
