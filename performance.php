<?php
/**
 * Plugin Name: Performance monorepo (not a real plugin)
 * Plugin URI: https://github.com/WordPress/performance
 * Description: The Performance monorepo is not a plugin rather a collection of performance features as plugins. Download <a href="https://wordpress.org/plugins/performance-lab/">Performance Lab</a> to install performance features instead. Otherwise, if wanting to contribute, please refer to the <a href="https://make.wordpress.org/performance/handbook/performance-lab/">handbook page</a>.
 * Author: WordPress Performance Team
 * Author URI: https://make.wordpress.org/performance/
 * Text Domain: performance-lab
 *
 * @package performance
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Notify the admin that the Performance monorepo is not a plugin, if they tried to install it as one.
 */
function perflab_monorepo_is_not_a_plugin(): void {
	// Note: The following notice is not translated because it is never published to WordPress.org and so it will never get translations.
	?>
	<div class="notice notice-error">
		<p>The Performance monorepo is not a plugin rather a collection of performance features as plugins. Download <a href="https://wordpress.org/plugins/performance-lab/">Performance Lab</a> to install performance features instead. Otherwise, if wanting to contribute, please refer to the <a href="https://make.wordpress.org/performance/handbook/performance-lab/">handbook page</a>.</p>
	</div>
	<?php
}

add_action( 'admin_notices', 'perflab_monorepo_is_not_a_plugin' );
