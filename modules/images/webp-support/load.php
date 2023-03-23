<?php
/**
 * Module Name: WebP Support Health Check
 * Description: Adds a WebP support check in Site Health status.
 * Experimental: No
 *
 * @package performance-lab
 * @since 1.0.0
 */

// Do not load the code if it is already loaded through another means.
if ( function_exists( 'webp_uploads_add_is_webp_supported_test' ) ) {
	return;
}

require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/hooks.php';
