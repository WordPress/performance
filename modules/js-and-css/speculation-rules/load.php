<?php
/**
 * Module Name: Speculation Rules
 * Description: Uses the Speculation Rules API to prerender linked URLs upon hover by default.
 * Experimental: Yes
 *
 * @package performance-lab
 * @since n.e.x.t
 */

// Define the constant.
if ( defined( 'SPECULATION_RULES_VERSION' ) ) {
	return;
}

define( 'SPECULATION_RULES_VERSION', 'Performance Lab ' . PERFLAB_VERSION );

require_once __DIR__ . '/class-plsr-url-pattern-prefixer.php';
require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/hooks.php';
require_once __DIR__ . '/settings.php';
