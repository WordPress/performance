<?php
/**
 * Tests for the REST endpoint, Site Health tab, and analyzer guards.
 *
 * @package ai-performance-advisor
 */

declare( strict_types = 1 );

class AIPA_Test_Rest_And_Site_Health extends WP_UnitTestCase {

	public function test_site_health_tab_is_registered(): void {
		$tabs = aipa_add_site_health_tab( array( '' => 'Status' ) );
		$this->assertArrayHasKey( 'ai-performance-advisor', $tabs );
	}

	public function test_site_health_tab_filter_ignores_non_array(): void {
		$this->assertSame( 'unexpected', aipa_add_site_health_tab( 'unexpected' ) );
	}

	public function test_rest_route_registered(): void {
		// rest_get_server() fires the rest_api_init action, which is where the
		// plugin registers its route (see hooks.php). Calling the registration
		// function directly would trip register_rest_route()'s doing-it-wrong notice.
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/ai-performance-advisor/v1/analyze', $routes );
	}

	public function test_permission_denied_for_subscriber(): void {
		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user );
		$result = aipa_rest_permission_check();
		$this->assertInstanceOf( WP_Error::class, $result );
	}

	public function test_permission_granted_for_admin(): void {
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		// On multisite the view_site_health_checks capability is reserved for super
		// admins, so elevate the user to ensure they genuinely hold the capability.
		if ( is_multisite() ) {
			grant_super_admin( $user );
		}
		wp_set_current_user( $user );
		$this->assertTrue( aipa_rest_permission_check() );
	}

	public function test_analyzer_errors_when_ai_unavailable(): void {
		add_filter( 'wp_supports_ai', '__return_false' );
		$analyzer = new AIPA_Analyzer();
		$result   = $analyzer->analyze();
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'aipa_ai_unavailable', $result->get_error_code() );
		remove_filter( 'wp_supports_ai', '__return_false' );
	}
}
