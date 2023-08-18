<?php
/**
 * Class PerformanceLab\REST_API\REST_Plugins_Controller
 *
 * @package   PerformanceLab\REST_API
 * Author: WordPress Performance Team
 * Author URI: https://make.wordpress.org/performance/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace PerformanceLab\REST_API;

use PerformanceLab\Plugin_Manager as Plugin_Manager;
use PerformanceLab\REST_API\REST_Route as REST_Route;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class for handling standalone performance plugin actions via REST endpoints.
 *
 * @since n.e.x.t
 * @access private
 * @ignore
 */
class REST_Plugins_Controller {

	/**
	 * Registers functionality.
	 *
	 * @since 1.90.0
	 */
	public static function register() {
		add_filter(
			'performance_lab_rest_routes',
			static function( $routes ) {
				return array_merge( $routes, self::get_rest_routes() );
			}
		);
	}

	/**
	 * Gets related REST routes.
	 *
	 * @since 1.90.0
	 *
	 * @return array List of REST_Route objects.
	 */
	private static function get_rest_routes() {
		$can_authenticate = static function() {
			return current_user_can( 'administrator' );
		};

		return array(
			new REST_Route(
				'plugins/install',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => static function( WP_REST_Request $request ) {
							$plugin    = $request->get_param( 'plugin' );
							$installed = Plugin_Manager::install( $plugin );

							$response = rest_ensure_response(
								array( 'response' => true === $installed )
							);
							return $response;
						},
						'permission_callback' => $can_authenticate,
					),
				)
			),
			new REST_Route(
				'plugins/activate',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => static function( WP_REST_Request $request ) {
							$plugin    = $request->get_param( 'plugin' );
							$activated = Plugin_Manager::activate( $plugin );

							$response = rest_ensure_response(
								array( 'response' => true === $activated )
							);
							return $response;
						},
						'permission_callback' => $can_authenticate,
					),
				)
			),
			new REST_Route(
				'plugins/deactivate',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => static function( WP_REST_Request $request ) {
							$plugin      = $request->get_param( 'plugin' );
							$deactivated = Plugin_Manager::deactivate( $plugin );

							$response = rest_ensure_response(
								array( 'response' => true === $deactivated )
							);
							return $response;
						},
						'permission_callback' => $can_authenticate,
					),
				)
			),
			new REST_Route(
				'plugins/uninstall',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => static function( WP_REST_Request $request ) {
							$plugin      = $request->get_param( 'plugin' );
							$uninstalled = Plugin_Manager::uninstall( $plugin );

							$response = rest_ensure_response(
								array( 'response' => true === $uninstalled )
							);
							return $response;
						},
						'permission_callback' => $can_authenticate,
					),
				)
			),
		);
	}
}
