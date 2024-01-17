<?php
/**
 * Module Name: Auto-sizes for Lazy-loaded Images
 * Description: This plugin implements the HTML spec for adding `sizes="auto"` to lazy-loaded images.
 * Experimental: Yes
 *
 * @package performance-lab
 * @since n.e.x.t
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define the constant.
if ( defined( 'IMAGE_AUTO_SIZES_VERSION' ) ) {
	return;
}

define( 'IMAGE_AUTO_SIZES_VERSION', 'Performance Lab ' . PERFLAB_VERSION );

require_once __DIR__ . '/hooks.php';
