<?php
/**
 * Tests for the REST endpoint and the Site Health tab.
 *
 * @package ai-performance-advisor
 */

declare( strict_types = 1 );

require_once __DIR__ . '/class-aipa-fake-context-provider.php';

class AIPA_Test_Rest_And_Site_Health extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_transient( AIPA_Analyzer::CACHE_KEY );
		remove_all_filters( 'aipa_pre_is_ai_available' );
		remove_all_filters( 'aipa_pre_generate_text' );
		remove_all_actions( 'aipa_register_context_providers' );
		parent::tear_down();
	}

	/**
	 * @covers ::aipa_add_site_health_tab
	 */
	public function test_site_health_tab_is_registered(): void {
		$tabs = aipa_add_site_health_tab( array( '' => 'Status' ) );
		$this->assertArrayHasKey( 'ai-performance-advisor', $tabs );
	}

	/**
	 * @covers ::aipa_add_site_health_tab
	 */
	public function test_site_health_tab_filter_ignores_non_array(): void {
		$this->assertSame( 'unexpected', aipa_add_site_health_tab( 'unexpected' ) );
	}

	/**
	 * @covers ::aipa_register_rest_routes
	 */
	public function test_rest_route_registered(): void {
		// rest_get_server() fires the rest_api_init action, which is where the
		// plugin registers its route (see hooks.php). Calling the registration
		// function directly would trip register_rest_route()'s doing-it-wrong notice.
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/ai-performance-advisor/v1/analyze', $routes );
	}

	/**
	 * @covers ::aipa_rest_permission_check
	 */
	public function test_permission_denied_for_subscriber(): void {
		$user = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user );
		$result = aipa_rest_permission_check();
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'aipa_forbidden', $result->get_error_code() );
	}

	/**
	 * @covers ::aipa_rest_permission_check
	 */
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

	/**
	 * @covers ::aipa_rest_analyze
	 */
	public function test_rest_analyze_returns_error_when_ai_unavailable(): void {
		add_filter( 'aipa_pre_is_ai_available', '__return_false' );

		$request = new WP_REST_Request( 'POST', '/ai-performance-advisor/v1/analyze' );
		$result  = aipa_rest_analyze( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'aipa_ai_unavailable', $result->get_error_code() );
		$data = $result->get_error_data();
		$this->assertSame( 500, $data['status'] );
	}

	/**
	 * @covers ::aipa_rest_analyze
	 */
	public function test_rest_analyze_returns_recommendations(): void {
		$this->use_hermetic_ai(
			(string) wp_json_encode(
				array(
					array(
						'title'   => 'Reduce server response time',
						'summary' => 'Enable object caching.',
					),
				)
			)
		);

		$request = new WP_REST_Request( 'POST', '/ai-performance-advisor/v1/analyze' );
		$request->set_param( 'refresh', true );
		$response = aipa_rest_analyze( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'recommendations', $data );
		$this->assertSame( 'Reduce server response time', $data['recommendations'][0]['title'] );
	}

	/**
	 * @covers ::aipa_render_site_health_tab
	 */
	public function test_render_tab_outputs_nothing_for_other_tabs(): void {
		$this->assertSame( '', $this->capture_tab( 'some-other-tab' ) );
	}

	/**
	 * @covers ::aipa_render_site_health_tab
	 */
	public function test_render_tab_shows_setup_notice_when_ai_unavailable(): void {
		add_filter( 'aipa_pre_is_ai_available', '__return_false' );
		$html = $this->capture_tab( 'ai-performance-advisor' );
		$this->assertStringContainsString( 'options-connectors.php', $html );
		$this->assertStringContainsString( 'notice', $html );
	}

	/**
	 * @covers ::aipa_render_site_health_tab
	 */
	public function test_render_tab_shows_analyze_ui_when_ai_available(): void {
		add_filter( 'aipa_pre_is_ai_available', '__return_true' );
		add_action( 'aipa_register_context_providers', array( $this, 'register_fake_provider' ) );

		$html = $this->capture_tab( 'ai-performance-advisor' );
		$this->assertStringContainsString( 'id="aipa-analyze"', $html );
		$this->assertStringContainsString( 'id="aipa-results"', $html );
		$this->assertStringContainsString( 'Fake provider', $html );
	}

	/**
	 * Forces AI availability and routes the model call through a canned response.
	 *
	 * @param string $response Canned model output.
	 */
	private function use_hermetic_ai( string $response ): void {
		add_filter( 'aipa_pre_is_ai_available', '__return_true' );
		add_filter(
			'aipa_pre_generate_text',
			static function () use ( $response ) {
				return $response;
			}
		);
		add_action( 'aipa_register_context_providers', array( $this, 'register_fake_provider' ) );
	}

	/**
	 * Replaces the default providers with a single hermetic one.
	 *
	 * @param AIPA_Context_Provider_Registry $registry The registry being assembled.
	 */
	public function register_fake_provider( AIPA_Context_Provider_Registry $registry ): void {
		foreach ( array( 'environment', 'site_health', 'site_health_tests', 'pagespeed', 'optimization_detective' ) as $key ) {
			$registry->unregister( $key );
		}
		$registry->register( new AIPA_Fake_Context_Provider() );
	}

	/**
	 * Captures the rendered Site Health tab content for the given tab slug.
	 *
	 * @param string $tab Tab slug.
	 * @return string Captured output.
	 */
	private function capture_tab( string $tab ): string {
		ob_start();
		aipa_render_site_health_tab( $tab );
		return (string) ob_get_clean();
	}
}
