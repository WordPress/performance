<?php
/**
 * Tests for audit-full-page-cache module.
 *
 * @package performance-lab
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
						'label'             => esc_html__( 'Page caching', 'performance-lab' ),
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
		$this->assertTrue( call_user_func( $route['permission_callback'] ) );
	}



}

