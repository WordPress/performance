<?php
/**
 * Load required files for the Dominant Color Images plugin functionality.
 *
 * This file ensures that all necessary helpers, hooks, and classes are loaded
 * for the plugin to operate correctly within WordPress.
 *
 * @package Performance Lab
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


// Define required constants.
if ( defined( 'DOMINANT_COLOR_IMAGES_VERSION' ) ) {
	return;
}

define( 'DOMINANT_COLOR_IMAGES_VERSION', '1.2.0' );

require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/hooks.php';
// @codeCoverageIgnoreEnd
