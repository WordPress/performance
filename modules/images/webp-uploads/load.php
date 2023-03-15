<?php
/**
 * Module Name: WebP Uploads
 * Description: Creates WebP versions for new JPEG image uploads if supported by the server.
 * Experimental: No
 *
 * @since   1.0.0
 * @package performance-lab
 */

/**
 * Require helper functions and specific integrations.
 */
// Do not load the code if it is already loaded through another means.
if ( function_exists( 'webp_uploads_create_sources_property' ) ) {
	return;
}

/**
 * Determines whether the feature can be loaded, or whether is already merged into WordPress core.
 *
 * If it cannot be loaded, it will add an action to display a WordPress admin notice.
 *
 * @since n.e.x.t
 *
 * @return bool Whether the feature can be loaded.
 */
function webp_uploads_can_load() {
	$can_load = require __DIR__ . '/can-load.php';
	if ( $can_load() ) {
		return true;
	}
	add_action(
		'admin_notices',
		function() {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'The WebP Uploads feature cannot be loaded from within the plugin since it is already merged into WordPress core.', 'performance-lab' )
			);
		}
	);
	return false;
}

// Do not run the plugin if conditions are not met.
if ( ! webp_uploads_can_load() ) {
	return;
}

require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/rest-api.php';
require_once __DIR__ . '/image-edit.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/hooks.php';
