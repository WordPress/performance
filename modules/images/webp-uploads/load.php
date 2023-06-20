<?php
/**
 * Module Name: WebP Uploads
 * Description: Creates WebP versions for new JPEG image uploads if supported by the server.
 * Experimental: No
 *
 * @since   1.0.0
 * @package performance-lab
 */

// Define the constant.
if ( defined( 'WEBP_UPLOADS_VERSION' ) ) {
	return;
}

define( 'WEBP_UPLOADS_VERSION', 'Performance Lab ' . PERFLAB_VERSION );

// Do not load the code if it is already loaded through another means.
if ( function_exists( 'webp_uploads_create_sources_property' ) ) {
	return;
}

// Do not load the code and show an admin notice instead if conditions are not met.
if ( ! require __DIR__ . '/can-load.php' ) {
	add_action(
		'admin_notices',
		static function() {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'The WebP Uploads feature cannot be loaded from within the plugin since it is already merged into WordPress core.', 'performance-lab' )
			);
		}
	);
	return;
}

require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/rest-api.php';
require_once __DIR__ . '/image-edit.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/hooks.php';
