<?php
/**
 * Tests for audit-enqueued-assets check.
 *
 * @package performance-lab
 * @group audit-enqueued-assets
 */

class Test_Audit_Enqueued_Assets extends WP_UnitTestCase {

	const WARNING_SCRIPTS_THRESHOLD = 31;
	const WARNING_STYLES_THRESHOLD  = 11;

	/**
	 * Tests perflab_aea_audit_enqueued_scripts() when transient is already set.
	 */
	public function test_perflab_aea_audit_enqueued_scripts_transient_already_set(): void {
		/**
		 * Prepare scenario for test.
		 */
		$this->mock_is_admin();
		$this->current_user_can_view_site_health_checks_cap();

		Audit_Assets_Transients_Set::set_script_transient_with_data( 3 );
		perflab_aea_audit_blocking_assets();
		$transient = get_transient( 'aea_blocking_assets' );
		$this->assertIsArray( $transient );
		$this->assertArrayHasKey( 'scripts', $transient );
		$this->assertEquals( 3, count( $transient['scripts'] ) );
		$this->assertEqualSets(
			array(
				array(
					'src'  => 'script.js',
					'size' => 1000,
				),
				array(
					'src'  => 'script.js',
					'size' => 1000,
				),
				array(
					'src'  => 'script.js',
					'size' => 1000,
				),
			),
			$transient['scripts']
		);
	}

	/**
	 * Tests perflab_aea_audit_enqueued_scripts() with no transient.
	 * Enqueued scripts ( not belonging to core /wp-includes/ ) will be saved in transient.
	 */
	public function test_perflab_aea_audit_enqueued_scripts(): void {
		/**
		 * Prepare scenario for test.
		 */
		$this->mock_is_admin();
		$this->current_user_can_view_site_health_checks_cap();

		wp_enqueue_script( 'script1', 'https://example1.com', array(), null );
		wp_enqueue_script( 'script2', '/wp-includes/example2.com', array(), null );
		wp_enqueue_script( 'script3', 'https://example3.com', array(), null );
		wp_dequeue_script( 'script3' );

		// Avoid deprecation warning due to related change in WordPress 6.4.
		remove_action( 'wp_print_styles', 'print_emoji_styles' );

		$this->mock_requests();
		perflab_aea_audit_blocking_assets();
		$transient = get_transient( 'aea_blocking_assets' );
		$this->assertIsArray( $transient );
		$this->assertArrayHasKey( 'scripts', $transient );
		$this->assertNotEmpty( $transient['scripts'] );

		$external_script = array_filter(
			$transient['scripts'],
			static function ( $item ) {
				return 'https://example1.com' === $item['src'];
			}
		);
		$this->assertEquals( 1, count( $external_script ) );
	}

	/**
	 * Tests perflab_aea_audit_enqueued_styles() when transient is already set.
	 */
	public function test_perflab_aea_audit_enqueued_styles_transient_already_set(): void {
		/**
		 * Prepare scenario for test.
		 */
		$this->mock_is_admin();
		$this->current_user_can_view_site_health_checks_cap();

		Audit_Assets_Transients_Set::set_style_transient_with_data( 3 );

		// Avoid deprecation warning due to related change in WordPress 6.4.
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		get_echo( 'wp_print_styles' );

		perflab_aea_audit_blocking_assets();
		$transient = get_transient( 'aea_blocking_assets' );
		$this->assertIsArray( $transient );
		$this->assertArrayHasKey( 'styles', $transient );
		$this->assertEquals( 3, count( $transient['styles'] ) );
		$this->assertEqualSets(
			array(
				array(
					'src'  => 'style.css',
					'size' => 1000,
				),
				array(
					'src'  => 'style.css',
					'size' => 1000,
				),
				array(
					'src'  => 'style.css',
					'size' => 1000,
				),
			),
			$transient['styles']
		);
	}

	/**
	 * Tests perflab_aea_audit_enqueued_styles() with no transient.
	 * Enqueued styles ( not belonging to core /wp-includes/ ) will be saved in transient.
	 */
	public function test_perflab_aea_audit_enqueued_styles(): void {
		/**
		 * Prepare scenario for test.
		 */
		$this->mock_is_admin();
		$this->current_user_can_view_site_health_checks_cap();

		wp_enqueue_style( 'style1', 'https://example1.com', array(), null );
		wp_enqueue_style( 'style2', '/wp-includes/example2.com', array() );
		wp_enqueue_style( 'style3', 'https://example3.com', array(), null );
		wp_dequeue_style( 'style3' );

		// Avoid deprecation warning due to related change in WordPress 6.4.
		remove_action( 'wp_print_styles', 'print_emoji_styles' );

		$this->mock_requests();
		perflab_aea_audit_blocking_assets();
		$transient = get_transient( 'aea_blocking_assets' );
		$this->assertIsArray( $transient );
		$this->assertArrayHasKey( 'styles', $transient );
		$this->assertNotEmpty( $transient['styles'] );

		$external_style = array_filter(
			$transient['styles'],
			static function ( $item ) {
				return 'https://example1.com' === $item['src'];
			}
		);
		$this->assertEquals( 1, count( $external_style ) );
	}

	/**
	 * Make sure perflab_aea_add_enqueued_assets_test adds the right information.
	 */
	public function test_perflab_aea_add_enqueued_assets_test(): void {
		$initial_tests = array(
			'direct' => array(
				'initial' => array(
					'label' => 'Label',
					'test'  => 'test',
				),
			),
		);

		$expected           = $initial_tests;
		$expected['direct'] = array_merge(
			$expected['direct'],
			Site_Health_Mock_Responses::return_added_test_info_site_health()['direct']
		);

		$this->assertEqualSets(
			$expected,
			perflab_aea_add_enqueued_assets_test( $initial_tests )
		);
	}

	/**
	 * Test perflab_aea_enqueued_js_assets_test() no transient saved.
	 */
	public function test_perflab_aea_enqueued_js_assets_test_no_transient(): void {
		$this->assertSame( array( 'omitted' => true ), perflab_aea_enqueued_js_assets_test() );
	}

	/**
	 * Test perflab_aea_enqueued_js_assets_test() with data in transient ( less than WARNING_SCRIPTS_threshold ).
	 */
	public function test_perflab_aea_enqueued_js_assets_test_with_assets_less_than_threshold(): void {
		Audit_Assets_Transients_Set::set_script_transient_with_data( 1 );
		$mocked_data = $this->mock_data_perflab_aea_enqueued_js_assets_test_callback( 1 );
		$this->assertEqualSets( $mocked_data, perflab_aea_enqueued_js_assets_test() );
	}

	/**
	 * Test perflab_aea_enqueued_js_assets_test() with data in transient ( more than WARNING_SCRIPTS_threshold ).
	 */
	public function test_perflab_aea_enqueued_js_assets_test_with_assets_more_than_threshold(): void {
		Audit_Assets_Transients_Set::set_script_transient_with_data( self::WARNING_SCRIPTS_THRESHOLD );
		$mocked_data = $this->mock_data_perflab_aea_enqueued_js_assets_test_callback( self::WARNING_SCRIPTS_THRESHOLD );
		$this->assertEqualSets( $mocked_data, perflab_aea_enqueued_js_assets_test() );
	}

	/**
	 * Test perflab_aea_enqueued_css_assets_test() no transient saved.
	 */
	public function test_perflab_aea_enqueued_css_assets_test_no_transient(): void {
		$this->assertSame( array( 'omitted' => true ), perflab_aea_enqueued_css_assets_test() );
	}

	/**
	 * Test perflab_aea_enqueued_css_assets_test() with data in transient ( less than WARNING_STYLES_threshold ).
	 */
	public function test_perflab_aea_enqueued_css_assets_test_with_assets_less_than_threshold(): void {
		Audit_Assets_Transients_Set::set_style_transient_with_data( 1 );
		$mocked_data = $this->mock_data_perflab_aea_enqueued_css_assets_test_callback( 1 );
		$this->assertEqualSets( $mocked_data, perflab_aea_enqueued_css_assets_test() );
	}

	/**
	 * Test perflab_aea_enqueued_css_assets_test() with data in transient ( more than WARNING_STYLES_threshold ).
	 */
	public function test_aea_enqueued_cdd_assets_test_with_assets_more_than_threshold(): void {
		Audit_Assets_Transients_Set::set_style_transient_with_data( self::WARNING_STYLES_THRESHOLD );
		$mocked_data = $this->mock_data_perflab_aea_enqueued_css_assets_test_callback( self::WARNING_STYLES_THRESHOLD );
		$this->assertEqualSets( $mocked_data, perflab_aea_enqueued_css_assets_test() );
	}

	/**
	 * Tests perflab_aea_invalidate_cache_transients() functionality.
	 */
	public function test_perflab_aea_invalidate_cache_transients(): void {
		Audit_Assets_Transients_Set::set_script_transient_with_data();
		Audit_Assets_Transients_Set::set_style_transient_with_data();
		perflab_aea_invalidate_cache_transients();
		$this->assertFalse( get_transient( 'aea_blocking_assets' ) );
	}

	/**
	 * Tests perflab_aea_clean_aea_audit_action() functionality.
	 */
	public function test_perflab_aea_clean_aea_audit_action(): void {
		Audit_Assets_Transients_Set::set_script_transient_with_data();
		Audit_Assets_Transients_Set::set_style_transient_with_data();
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'clean_aea_audit' );
		$_GET['action']       = 'clean_aea_audit';
		$this->current_user_can_view_site_health_checks_cap();
		$redirected_url = null;
		add_filter(
			'wp_redirect',
			static function ( $url ) use ( &$redirected_url ) {
				$redirected_url = $url;
				return false;
			}
		);
		$_REQUEST['_wp_http_referer'] = add_query_arg(
			array(
				'_wpnonce' => 'foo',
				'action'   => 'bar',
			),
			home_url( '/' )
		);
		perflab_aea_clean_aea_audit_action();
		$this->assertSame( home_url( '/' ), $redirected_url );
		$this->assertFalse( get_transient( 'aea_blocking_assets' ) );
	}

	/**
	 * Mocks the current screen to be the dashboard.
	 */
	public function mock_is_admin(): void {
		set_current_screen( 'dashboard' );
	}

	/**
	 * Adds view_site_health_checks capability to current user.
	 */
	public function current_user_can_view_site_health_checks_cap(): void {
		$current_user = wp_get_current_user();
		$current_user->add_cap( 'view_site_health_checks' );
	}

	/**
	 * @param int $number_of_assets Number of assets mocked.
	 * @return array<string, mixed>
	 */
	public function mock_data_perflab_aea_enqueued_js_assets_test_callback( int $number_of_assets = 5 ): array {
		if ( $number_of_assets < self::WARNING_SCRIPTS_THRESHOLD ) {
			return Site_Health_Mock_Responses::return_aea_enqueued_js_assets_test_callback_less_than_threshold( $number_of_assets );
		}
		return Site_Health_Mock_Responses::return_aea_enqueued_js_assets_test_callback_more_than_threshold( $number_of_assets );
	}

	/**
	 * @param int $number_of_assets Number of styles mocked.
	 * @return array<string, mixed>
	 */
	public function mock_data_perflab_aea_enqueued_css_assets_test_callback( int $number_of_assets = 5 ): array {
		if ( $number_of_assets < self::WARNING_STYLES_THRESHOLD ) {
			return Site_Health_Mock_Responses::return_aea_enqueued_css_assets_test_callback_less_than_threshold( $number_of_assets );
		}
		return Site_Health_Mock_Responses::return_aea_enqueued_css_assets_test_callback_more_than_threshold( $number_of_assets );
	}

	/**
	 * Mocks HTTP requests for tests.
	 */
	public function mock_requests(): void {
		$mock_html  = '<!DOCTYPE html><head>';
		$mock_html .= get_echo( 'wp_head' );
		$mock_html .= '</head><body>';
		$mock_html .= get_echo( 'wp_footer' );
		$mock_html .= '</body></html>';

		remove_all_filters( 'pre_http_request' );
		add_filter(
			'pre_http_request',
			static function ( $preempt, $parsed_args ) use ( $mock_html ) {
				// Mock a HEAD request to return a content length header.
				if ( isset( $parsed_args['method'] ) && 'HEAD' === $parsed_args['method'] ) {
					return array(
						'response' => array(
							'code' => 200,
						),
						'body'     => '',
						'headers'  => array(
							'content-length' => '10000', // Mocked size of the asset.
						),
					);
				}

				// Mock a GET request to return the HTML content.
				return array(
					'response' => array(
						'code' => 200,
					),
					'body'     => $mock_html,
					'headers'  => array(),
				);
			},
			10,
			2
		);
	}
}
