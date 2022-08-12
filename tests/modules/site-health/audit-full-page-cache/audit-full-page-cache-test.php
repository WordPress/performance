<?php
/**
 * Tests for audit-full-page-cache module.
 *
 * @package performance-lab
 * @group audit-full-page-cache
 */

class Audit_Full_Page_Cache_Tests extends WP_UnitTestCase {

	const REST_NAMESPACE = 'performance-lab/v1';
	const REST_ROTE      = '/tests/page-cache';

	/**
	 * Tests perflab_afpc_add_full_page_cache_test().
	 */
	public function test_perflab_afpc_add_full_page_cache_test() {
		$result = perflab_afpc_add_full_page_cache_test( array() );
		$this->assertEqualSets(
			array(
				'async' => array(
					'perflab_page_cache' => array(
						'label'             => __( 'Page caching', 'performance-lab' ),
						'test'              => rest_url( 'performance-lab/v1/tests/page-cache' ),
						'has_rest'          => true,
						'async_direct_test' => 'perflab_afpc_page_cache_test',
					),
				),
			),
			$result
		);
	}

	/**
	 * Tests all possible scenarios given by dataProvider.
	 *
	 * @dataProvider provider_perflab_afpc_page_cache_test
	 * @covers ::perflab_afpc_page_cache_test()
	 * @covers ::perflab_afpc_get_page_cache_detail()
	 * @covers ::perflab_afpc_get_page_cache_detail()
	 * @covers ::perflab_afpc_get_page_cache_headers()
	 * @covers ::perflab_afpc_check_for_page_caching()
	 */
	public function test_perflab_afpc_page_cache_test( $responses, $expected_status, $expected_label, $good_basic_auth = null, $delay_the_response = false ) {
		$badge_color = array(
			'critical'    => 'red',
			'recommended' => 'orange',
			'good'        => 'green',
		);

		$expected_props = array(
			'badge'  => array(
				'label' => __( 'Performance', 'performance-lab' ),
				'color' => $badge_color[ $expected_status ],
			),
			'test'   => 'perflab_page_cache',
			'status' => $expected_status,
			'label'  => $expected_label,
		);

		if ( null !== $good_basic_auth ) {
			$_SERVER['PHP_AUTH_USER'] = 'admin';
			$_SERVER['PHP_AUTH_PW']   = 'password';
		}

		$threshold = 10;
		if ( $delay_the_response ) {
			add_filter(
				'perflab_afpc_page_cache_good_response_time_threshold',
				static function () use ( $threshold ) {
					return $threshold;
				}
			);
		}

		add_filter(
			'pre_http_request',
			function ( $r, $parsed_args ) use ( &$responses, &$is_unauthorized, $good_basic_auth, $delay_the_response, $threshold ) {

				$expected_response = array_shift( $responses );

				if ( $delay_the_response ) {
					usleep( $threshold * 1000 + 1 );
				}

				if ( 'unauthorized' === $expected_response ) {
					$is_unauthorized = true;

					return array(
						'response' => array(
							'code'    => 401,
							'message' => 'Unauthorized',
						),
					);
				}

				if ( null !== $good_basic_auth ) {
					$this->assertArrayHasKey(
						'Authorization',
						$parsed_args['headers']
					);
				}

				$this->assertIsArray( $expected_response );

				return array(
					'headers'  => $expected_response,
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
				);
			},
			20,
			2
		);

		$actual = perflab_afpc_page_cache_test();
		$this->assertArrayHasKey( 'description', $actual );
		$this->assertArrayHasKey( 'actions', $actual );
		if ( $is_unauthorized ) {
			$this->assertStringContainsString( 'Unauthorized', $actual['description'] );
		} else {
			$this->assertStringNotContainsString( 'Unauthorized', $actual['description'] );
		}

		$this->assertEquals(
			$expected_props,
			wp_array_slice_assoc( $actual, array_keys( $expected_props ) )
		);
	}

	/**
	 * Gets response data for test_perflab_afpc_page_cache_test().
	 *
	 * @return array[]
	 */
	public function provider_perflab_afpc_page_cache_test() {
		$recommended_label = 'Page caching is not detected but the server response time is OK';
		$good_label        = 'Page caching is detected and the server response time is good';
		$critical_label    = 'Page caching is not detected and the server response time is slow';
		$error_label       = 'Unable to detect the presence of page caching';

		return array(
			'basic-auth-fail'                          => array(
				'responses'       => array(
					'unauthorized',
				),
				'expected_status' => 'recommended',
				'expected_label'  => $error_label,
				'good_basic_auth' => false,
			),
			'no-cache-control'                         => array(
				'responses'          => array_fill( 0, 3, array() ),
				'expected_status'    => 'critical',
				'expected_label'     => $critical_label,
				'good_basic_auth'    => null,
				'delay_the_response' => true,
			),
			'no-cache'                                 => array(
				'responses'       => array_fill( 0, 3, array( 'cache-control' => 'no-cache' ) ),
				'expected_status' => 'recommended',
				'expected_label'  => $recommended_label,
			),
			'no-cache-arrays'                          => array(
				'responses'       => array_fill( 0, 3, array( 'cache-control' => array( 'no-cache', 'no-store' ) ) ),
				'expected_status' => 'recommended',
				'expected_label'  => $recommended_label,
			),
			'no-cache-with-delayed-response'           => array(
				'responses'          => array_fill( 0, 3, array( 'cache-control' => 'no-cache' ) ),
				'expected_status'    => 'critical',
				'expected_label'     => $critical_label,
				'good_basic_auth'    => null,
				'delay_the_response' => true,
			),
			'age'                                      => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'age' => '1345' )
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
			),
			'cache-control-max-age'                    => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'cache-control' => 'public; max-age=600' )
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
			),
			'etag'                                     => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'etag' => '"1234567890"' )
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
			),
			'cache-control-max-age-after-2-requests'   => array(
				'responses'       => array(
					array(),
					array(),
					array( 'cache-control' => 'public; max-age=600' ),
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
			),
			'cache-control-with-future-expires'        => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'expires' => gmdate( 'r', time() + MINUTE_IN_SECONDS * 10 ) )
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
			),
			'cache-control-with-past-expires'          => array(
				'responses'          => array_fill(
					0,
					3,
					array( 'expires' => gmdate( 'r', time() - MINUTE_IN_SECONDS * 10 ) )
				),
				'expected_status'    => 'critical',
				'expected_label'     => $critical_label,
				'good_basic_auth'    => null,
				'delay_the_response' => true,
			),
			'cache-control-with-basic-auth'            => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'cache-control' => 'public; max-age=600' )
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
				'good_basic_auth' => true,
			),
			'cf-cache-status'                          => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'cf-cache-status' => 'HIT: 1' )
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
			),
			'cf-cache-status-without-header-and-delay' => array(
				'responses'          => array_fill(
					0,
					3,
					array( 'cf-cache-status' => 'MISS' )
				),
				'expected_status'    => 'recommended',
				'expected_label'     => $recommended_label,
				'good_basic_auth'    => null,
				'delay_the_response' => false,
			),
			'cf-cache-status-with-delay'               => array(
				'responses'          => array_fill(
					0,
					3,
					array( 'cf-cache-status' => 'MISS' )
				),
				'expected_status'    => 'critical',
				'expected_label'     => $critical_label,
				'good_basic_auth'    => null,
				'delay_the_response' => true,
			),
			'x-cache-enabled'                          => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'x-cache-enabled' => 'true' )
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
			),
			'x-cache-enabled-with-delay'               => array(
				'responses'          => array_fill(
					0,
					3,
					array( 'x-cache-enabled' => 'false' )
				),
				'expected_status'    => 'critical',
				'expected_label'     => $critical_label,
				'good_basic_auth'    => null,
				'delay_the_response' => true,
			),
			'x-cache-disabled'                         => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'x-cache-disabled' => 'off' )
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
			),
			'cf-apo-via'                               => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'cf-apo-via' => 'tcache' )
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
			),
			'cf-edge-cache'                            => array(
				'responses'       => array_fill(
					0,
					3,
					array( 'cf-edge-cache' => 'cache' )
				),
				'expected_status' => 'good',
				'expected_label'  => $good_label,
			),
		);
	}

	/**
	 * Tests Rest endpoint registration.
	 */
	public function test_perflab_afpc_register_async_test_endpoints() {
		$server = rest_get_server();
		$routes = $server->get_routes();

		$endpoint = '/' . self::REST_NAMESPACE . self::REST_ROTE;
		$this->assertArrayHasKey( $endpoint, $routes );

		$route = $routes[ $endpoint ];
		$this->assertCount( 1, $route );

		$route = current( $route );
		$this->assertEquals(
			array( WP_REST_Server::READABLE => true ),
			$route['methods']
		);

		$this->assertEquals(
			'perflab_afpc_page_cache_test',
			$route['callback']
		);

		$this->assertIsCallable( $route['permission_callback'] );

		if ( current_user_can( 'view_site_health_checks' ) ) {
			$this->assertTrue( call_user_func( $route['permission_callback'] ) );
		} else {
			$this->assertFalse( call_user_func( $route['permission_callback'] ) );
		}

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );
		$this->assertFalse( call_user_func( $route['permission_callback'] ) );

		$user = wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		if ( is_multisite() ) {
			// Site health cap is only available for super admins in Multi sites.
			grant_super_admin( $user->ID );
		}
		$this->assertTrue( call_user_func( $route['permission_callback'] ) );
	}

}

