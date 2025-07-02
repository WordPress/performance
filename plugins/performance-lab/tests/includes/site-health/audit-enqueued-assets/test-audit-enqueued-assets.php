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
	 * Mocked responses for HTTP requests.
	 *
	 * @var array<string, mixed>
	 */
	private $mocked_responses = array();

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

		$this->add_mock_responses(
			array(
				array(
					'url'      => home_url( '/' ),
					'response' => array(
						'code' => 200,
						'body' => $this->get_mocked_html(),
					),
				),
				array(
					'url'      => 'https://example1.com',
					'response' => array(
						'code' => 200,
						'body' => 'console.log("Example 1");',
					),
				),
				array(
					'url'      => '/wp-includes/example2.com',
					'response' => array(
						'code' => 200,
						'body' => 'console.log("Example 2");',
					),
				),
			)
		);
		$this->mock_request();
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

		$this->add_mock_responses(
			array(
				array(
					'url'      => home_url( '/' ),
					'response' => array(
						'code' => 200,
						'body' => $this->get_mocked_html(),
					),
				),
				array(
					'url'      => 'https://example1.com',
					'response' => array(
						'code' => 200,
						'body' => 'body { background-color: red; }',
					),
				),
				array(
					'url'      => '/wp-includes/example2.com',
					'response' => array(
						'code' => 200,
						'body' => 'body { background-color: blue; }',
					),
				),
			)
		);

		$this->mock_request();
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
	 * Tests perflab_aea_audit_blocking_assets functionality.
	 *
	 * @covers ::perflab_aea_audit_blocking_assets
	 * @dataProvider data_perflab_aea_audit_blocking_assets
	 *
	 * @param array<string|mixed>|WP_Error $response The response to be tested.
	 * @param bool                         $expected The expected result of the test.
	 */
	public function test_perflab_aea_audit_blocking_assets( $response, bool $expected ): void {
		$this->mock_is_admin();
		$this->current_user_can_view_site_health_checks_cap();
		$this->add_mock_responses(
			array(
				array(
					'url'      => home_url( '/' ),
					'response' => $response,
				),
			)
		);
		$this->mock_request();

		// Avoid deprecation warning due to related change in WordPress 6.4.
		remove_action( 'wp_print_styles', 'print_emoji_styles' );

		delete_transient( 'aea_blocking_assets' );
		perflab_aea_audit_blocking_assets();
		$transient = get_transient( 'aea_blocking_assets' );
		if ( $expected ) {
			$this->assertIsArray( $transient );
			$this->assertArrayHasKey( 'styles', $transient );
			$this->assertNotEmpty( $transient['styles'] );
		} else {
			$this->assertFalse( $transient );
		}
	}

	/**
	 * Data provider for test_perflab_aea_audit_blocking_assets.
	 *
	 * @return array<string, array<mixed>>
	 */
	public function data_perflab_aea_audit_blocking_assets(): array {
		wp_enqueue_script( 'script1', 'https://script1.com', array(), null );
		wp_enqueue_script( 'script2', '/wp-includes/script2.com', array(), null );
		wp_enqueue_script( 'script-async', 'https://async-script.com', array(), null, true );
		wp_enqueue_script( 'script-defer', 'https://defer-script.com', array(), null, true );
		wp_enqueue_script_module( 'noscript', 'https://non-javascript.com', array(), null );

		wp_enqueue_style( 'style1', 'https://style1.com', array(), null );
		wp_enqueue_style( 'style2', '/wp-includes/style2.com', array(), null );
		wp_enqueue_style( 'style-print', 'https://print-style.com', array(), null, 'print' );
		wp_enqueue_style( 'style-no-href', '', array(), null );

		add_filter(
			'script_loader_tag',
			static function ( $tag, $handle ) {
				if ( 'script-async' === $handle ) {
					$tag = str_replace( '<script ', '<script async ', $tag );
				} elseif ( 'script-defer' === $handle ) {
					$tag = str_replace( '<script ', '<script defer ', $tag );
				}
				return $tag;
			},
			10,
			2
		);

		add_filter(
			'style_loader_tag',
			static function ( $tag, $handle ) {
				if ( 'style-no-href' === $handle ) {
					$tag = str_replace( 'href=""', ' ', $tag );
				}
				return $tag;
			},
			10,
			2
		);

		$this->add_mock_responses(
			array(
				array(
					'url'      => 'https://script1.com',
					'response' => array(
						'code' => 200,
						'body' => 'console.log("Script 1 loaded");',
					),
				),
				array(
					'url'      => '/wp-includes/script2.com',
					'response' => array(
						'code' => 200,
						'body' => 'console.log("Script 2 loaded");',
					),
				),
				array(
					'url'      => 'https://async-script.com',
					'response' => array(
						'code' => 200,
						'body' => '',
					),
				),
				array(
					'url'      => 'https://defer-script.com',
					'response' => array(
						'code' => 200,
						'body' => '',
					),
				),
				array(
					'url'      => 'https://non-javascript.com',
					'response' => array(
						'code' => 200,
						'body' => 'This is a non-JavaScript resource.',
					),
				),
				array(
					'url'      => 'https://style1.com',
					'response' => array(
						'code' => 200,
						'body' => 'body { background-color: red; }',
					),
				),
				array(
					'url'      => '/wp-includes/style2.com',
					'response' => array(
						'code' => 200,
						'body' => 'body { background-color: blue; }',
					),
				),
				array(
					'url'      => 'https://print-style.com',
					'response' => array(
						'code' => 200,
						'body' => 'body { color: green; }',
					),
				),
			)
		);

		return array(
			'home_page_request_error'               => array(
				'response' => new WP_Error( 'error', 'Error message' ),
				'expected' => false,
			),
			'home_page_request_non_200_status_code' => array(
				'response' => array(
					'code' => 404,
				),
				'expected' => false,
			),
			'home_page_request_empty_body'          => array(
				'response' => array(
					'code' => 200,
					'body' => '',
				),
				'expected' => false,
			),
			'other_conditions'                      => array(
				'response' => array(
					'code' => 200,
					'body' => $this->get_mocked_html(),
				),
				'expected' => true,
			),
		);
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

	public function get_mocked_html(): string {
		$mock_html  = '<!DOCTYPE html><head>';
		$mock_html .= get_echo( 'wp_head' );
		$mock_html .= '</head><body>';
		$mock_html .= get_echo( 'wp_footer' );
		$mock_html .= '</body></html>';
		return $mock_html;
	}

	/**
	 * Adds a mocked response for a specific URL.
	 *
	 * @param array<string|mixed> $responses An array containing the URLs and the mocked responses.
	 */
	public function add_mock_responses( array $responses ): void {
		foreach ( $responses as $response ) {
			$this->mocked_responses[ $response['url'] ] = $response['response'];
		}
	}

	/**
	 * Mocks HTTP requests for tests.
	 */
	public function mock_request(): void {
		remove_all_filters( 'pre_http_request' );
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) {
				if ( isset( $this->mocked_responses[ $url ] ) ) {
					if ( is_wp_error( $this->mocked_responses[ $url ] ) ) {
						return $this->mocked_responses[ $url ];
					}

					return array(
						'response' => $this->mocked_responses[ $url ],
						'body'     => $this->mocked_responses[ $url ]['body'] ?? '',
					);
				}
				return $preempt;
			},
			10,
			3
		);
	}
}
