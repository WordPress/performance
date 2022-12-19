<?php
/**
 * Actions to run when the module gets activated.
 *
 * @since n.e.x.t
 * @package performance-lab
 */

return function() {
	update_option( 'test_demo_module_activation_status', 'activated' );
};
