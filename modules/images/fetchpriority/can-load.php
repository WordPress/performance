<?php
/**
 * Can load function to determine if the Fetchpriority feature is already available in WordPress core.
 *
 * @since   n.e.x.t
 * @package performance-lab
 */

return static function() {
	return ! function_exists( 'wp_get_loading_optimization_attributes' );
};
