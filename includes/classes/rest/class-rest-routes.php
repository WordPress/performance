<?php
/**
 * Class PerformanceLab\REST_API\REST_Routes
 *
 * @package   PerformanceLab\REST_API
 * Author: WordPress Performance Team
 * Author URI: https://make.wordpress.org/performance/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace PerformanceLab\REST_API;

defined( 'ABSPATH' ) || exit;

/**
 * Class managing REST API routes.
 *
 * @since n.e.x.t
 * @access private
 * @ignore
 */
final class REST_Routes {

	const REST_ROOT = 'performance-lab/v1';

	/**
	 * Registers functionality through WordPress hooks.
	 *
	 * @since n.e.x.t
	 */
	public function register() {
		add_action(
			'rest_api_init',
			function() {
				$this->register_routes();
			}
		);
	}

	/**
	 * Registers all REST routes.
	 *
	 * @since n.e.x.t
	 */
	private function register_routes() {
		$routes = $this->get_routes();

		foreach ( $routes as $route ) {
			$route->register();
		}
	}

	/**
	 * Gets available REST routes.
	 *
	 * @since n.e.x.t
	 *
	 * @return array List of REST_Route instances.
	 */
	private function get_routes() {
		$routes = array();

		/**
		 * Filters the list of available REST routes.
		 *
		 * @since n.e.x.t
		 *
		 * @param array $routes List of REST_Route objects.
		 */
		return apply_filters( 'performance_lab_rest_routes', $routes );
	}
}
