<?php
/**
 * Actions to run when the module gets deactivated.
 *
 * @since n.e.x.t
 * @package performance-lab
 */

return function() {
	update_option( 'test_demo_module_activation_status', 'deactivated' );
};
