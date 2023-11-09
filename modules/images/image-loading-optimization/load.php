<?php
/**
 * Module Name: Image Loading Optimization
 * Description: Improves accuracy of optimizing the loading of the LCP image by leveraging client-side detection with real user metrics. Also enables output buffering of template rendering which can be filtered.
 * Experimental: Yes
 *
 * @package performance-lab
 * @since n.e.x.t
 */

// Define the constant.
if ( defined( 'IMAGE_LOADING_OPTIMIZATION_VERSION' ) ) {
	return;
}

define( 'IMAGE_LOADING_OPTIMIZATION_VERSION', 'Performance Lab ' . PERFLAB_VERSION );

// Do not load the code if it is already loaded through another means.
if ( function_exists( 'ilo_buffer_output' ) ) {
	return;
}

require_once __DIR__ . '/hooks.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/detection.php';
