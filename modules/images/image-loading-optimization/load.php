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

// Storage logic.
require_once __DIR__ . '/class-ilo-data-validation-exception.php';
require_once __DIR__ . '/class-ilo-url-metric.php';
require_once __DIR__ . '/class-ilo-url-metrics-group.php';
require_once __DIR__ . '/class-ilo-grouped-url-metrics.php';
require_once __DIR__ . '/storage/lock.php';
require_once __DIR__ . '/storage/post-type.php';
require_once __DIR__ . '/storage/data.php';
require_once __DIR__ . '/storage/rest-api.php';

require_once __DIR__ . '/detection.php';

require_once __DIR__ . '/class-ilo-html-tag-processor.php';
require_once __DIR__ . '/optimization.php';
