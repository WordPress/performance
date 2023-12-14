<?php
/**
 * Module Name: Auto Sizes for Lazy-loaded Images.
 * Description: Implements auto sizes for lazy loaded images.
 * Experimental: Yes
 *
 * @package performance-lab
 * @since n.e.x.t
 */

// Define the constant.
if ( defined( 'IMAGE_AUTO_SIZES' ) ) {
	return;
}

define( 'IMAGE_AUTO_SIZES', 'Performance Lab ' . PERFLAB_VERSION );

require_once __DIR__ . '/hooks.php';
