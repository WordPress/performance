<?php
/**
 * Actions to run when the module gets activated.
 *
 * @since 1.8.0
 * @package performance-lab
 */

return static function () {
	update_option( 'test_demo_module_activation_status', 'activated' );
};
