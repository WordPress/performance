<?php
/**
 * Module Name: Autoloaded Options Health Check
 * Description: Adds a check for autoloaded options in Site Health status.
 * Experimental: Yes
 *
 * @package performance-lab
 * @since 1.0.0
 */

// Do not load the code if it is already loaded through another means.
if ( function_exists( 'perflab_aao_add_autoloaded_options_test' ) ) {
	return;
}

require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/hooks.php';
