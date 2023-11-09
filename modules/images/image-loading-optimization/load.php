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

require_once __DIR__ . '/hooks.php';
require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/detection.php';
