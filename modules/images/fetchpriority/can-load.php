<?php
/**
 * Can load function to determine if the Fetchpriority feature is already available in WordPress core.
 *
 * @since   2.5.0
 * @package performance-lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

return static function() {
	return ! function_exists( 'wp_get_loading_optimization_attributes' );
};
