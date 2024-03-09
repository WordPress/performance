<?php
/**
 * Module Name: Enqueued Assets Health Check
 * Description: Adds a CSS and JS resource check in Site Health status.
 * Experimental: Yes
 *
 * @package performance-lab
 * @since 1.0.0
 */

// Do not load the code if it is already loaded through another means.
if ( function_exists( 'perflab_aea_audit_enqueued_scripts' ) ) {
	return;
}

require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/hooks.php';
