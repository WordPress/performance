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
	 * Test 'perflab_aea_audit_blocking_assets' is added to 'admin_init' action.
	 */
	public function test_perflab_aea_audit_blocking_assets_hook(): void {
		$this->assertEquals( 10, has_action( 'admin_init', 'perflab_aea_audit_blocking_assets' ) );
	}

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
		wp_enqueue_script( 'script2', '/wp-includes/example2.js', array(), null );
		wp_enqueue_script( 'script3', 'https://example3.com', array(), null );
		wp_dequeue_script( 'script3' );
		wp_enqueue_script( 'script-async', 'https://async-script.com', array(), null, true );
		wp_enqueue_script( 'script-defer', 'https://defer-script.com', array(), null, true );
		wp_enqueue_script( 'type-noscript', 'https://non-javascript.com', array(), null );

		add_filter(
			'wp_script_attributes',
			static function ( $attributes ) {
				if ( 'script-async-js' === $attributes['id'] ) {
					$attributes['async'] = true;
				} elseif ( 'script-defer-js' === $attributes['id'] ) {
					$attributes['defer'] = true;
				} elseif ( 'type-noscript-js' === $attributes['id'] ) {
					$attributes['type'] = 'noscript';
				}
				return $attributes;
			}
		);

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
					'url'      => home_url( '/wp-includes/example2.js' ),
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

		$internal_script = array_filter(
			$transient['scripts'],
			static function ( $item ) {
				return home_url( '/wp-includes/example2.js' ) === $item['src'];
			}
		);
		$this->assertEquals( 1, count( $internal_script ) );

		$async_script = array_filter(
			$transient['scripts'],
			static function ( $item ) {
				return 'https://async-script.com' === $item['src'];
			}
		);
		$this->assertEmpty( $async_script );

		$defer_script = array_filter(
			$transient['scripts'],
			static function ( $item ) {
				return 'https://defer-script.com' === $item['src'];
			}
		);
		$this->assertEmpty( $defer_script );

		$noscript_script = array_filter(
			$transient['scripts'],
			static function ( $item ) {
				return 'https://non-javascript.com' === $item['src'];
			}
		);
		$this->assertEmpty( $noscript_script );
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
		wp_enqueue_style( 'style2', '/wp-includes/example2.css', array(), null );
		wp_enqueue_style( 'style3', 'https://example3.com', array(), null );
		wp_dequeue_style( 'style3' );
		wp_enqueue_style( 'style-print', 'https://print-style.com', array(), null, 'print' );
		wp_enqueue_style( 'style-no-href', 'https://no-href-style.com', array(), null );

		// Filter to remove href attribute from a specific style handle.
		add_filter(
			'style_loader_tag',
			static function ( $tag, $handle ) {
				if ( 'style-no-href' === $handle ) {
					$tag = str_replace( 'href=\'https://no-href-style.com\'', ' ', $tag );
				}
				return $tag;
			},
			10,
			2
		);

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
					'url'      => home_url( '/wp-includes/example2.css' ),
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

		$internal_style = array_filter(
			$transient['styles'],
			static function ( $item ) {
				return home_url( '/wp-includes/example2.css' ) === $item['src'];
			}
		);
		$this->assertEquals( 1, count( $internal_style ) );

		$print_style = array_filter(
			$transient['styles'],
			static function ( $item ) {
				return 'https://print-style.com' === $item['src'];
			}
		);
		$this->assertEmpty( $print_style );

		$no_href_style = array_filter(
			$transient['styles'],
			static function ( $item ) {
				return 'https://no-href-style.com' === $item['src'];
			}
		);
		$this->assertEmpty( $no_href_style );
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
	 * Test perflab_aea_enqueued_blocking_scripts() no transient saved.
	 */
	public function test_perflab_aea_enqueued_js_assets_test_no_transient(): void {
		$this->assertNull( perflab_aea_enqueued_blocking_scripts() );
	}

	/**
	 * Test perflab_aea_enqueued_blocking_scripts() with data in transient ( less than WARNING_SCRIPTS_threshold ).
	 */
	public function test_perflab_aea_enqueued_js_assets_test_with_assets_less_than_threshold(): void {
		Audit_Assets_Transients_Set::set_script_transient_with_data( 1 );
		$mocked_data = $this->mock_data_perflab_aea_enqueued_js_assets_test_callback( 1 );
		$this->assertEqualSets( $mocked_data, perflab_aea_enqueued_blocking_scripts() );
	}

	/**
	 * Test perflab_aea_enqueued_blocking_scripts() with data in transient ( more than WARNING_SCRIPTS_threshold ).
	 */
	public function test_perflab_aea_enqueued_js_assets_test_with_assets_more_than_threshold(): void {
		Audit_Assets_Transients_Set::set_script_transient_with_data( self::WARNING_SCRIPTS_THRESHOLD );
		$mocked_data = $this->mock_data_perflab_aea_enqueued_js_assets_test_callback( self::WARNING_SCRIPTS_THRESHOLD );
		$this->assertEqualSets( $mocked_data, perflab_aea_enqueued_blocking_scripts() );
	}

	/**
	 * Test perflab_aea_enqueued_blocking_styles() no transient saved.
	 */
	public function test_perflab_aea_enqueued_css_assets_test_no_transient(): void {
		$this->assertNull( perflab_aea_enqueued_blocking_styles() );
	}

	/**
	 * Test perflab_aea_enqueued_blocking_styles() with data in transient ( less than WARNING_STYLES_threshold ).
	 */
	public function test_perflab_aea_enqueued_css_assets_test_with_assets_less_than_threshold(): void {
		Audit_Assets_Transients_Set::set_style_transient_with_data( 1 );
		$mocked_data = $this->mock_data_perflab_aea_enqueued_css_assets_test_callback( 1 );
		$this->assertEqualSets( $mocked_data, perflab_aea_enqueued_blocking_styles() );
	}

	/**
	 * Test perflab_aea_enqueued_blocking_styles() with data in transient ( more than WARNING_STYLES_threshold ).
	 */
	public function test_aea_enqueued_cdd_assets_test_with_assets_more_than_threshold(): void {
		Audit_Assets_Transients_Set::set_style_transient_with_data( self::WARNING_STYLES_THRESHOLD );
		$mocked_data = $this->mock_data_perflab_aea_enqueued_css_assets_test_callback( self::WARNING_STYLES_THRESHOLD );
		$this->assertEqualSets( $mocked_data, perflab_aea_enqueued_blocking_styles() );
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
	 * Tests perflab_aea_audit_blocking_assets functionality when the home request fails.
	 *
	 * @covers ::perflab_aea_audit_blocking_assets
	 * @dataProvider data_perflab_aea_audit_blocking_assets_home_request_failure
	 *
	 * @param array<string|mixed>|WP_Error $mocked_response The mocked response to simulate the HTTP request.
	 */
	public function test_perflab_aea_audit_blocking_assets_home_request_failure( $mocked_response ): void {
		$this->mock_is_admin();
		$this->current_user_can_view_site_health_checks_cap();
		$this->add_mock_responses( array( $mocked_response ) );
		$this->mock_request();

		// Avoid deprecation warning due to related change in WordPress 6.4.
		remove_action( 'wp_print_styles', 'print_emoji_styles' );

		delete_transient( 'aea_blocking_assets' );
		perflab_aea_audit_blocking_assets();
		$transient = get_transient( 'aea_blocking_assets' );
		$this->assertFalse( $transient );
	}

	/**
	 * Data provider for test_perflab_aea_audit_blocking_assets_home_request_failure.
	 *
	 * @return array<string, array{ mocked_responses: array{ url: string, response: WP_Error|array{ code: positive-int, body: string } } }>
	 */
	public function data_perflab_aea_audit_blocking_assets_home_request_failure(): array {
		return array(
			'home_page_request_error'               => array(
				'mocked_responses' => array(
					'url'      => home_url( '/' ),
					'response' => new WP_Error( 'error', 'Error message' ),
				),
			),
			'home_page_request_non_200_status_code' => array(
				'mocked_responses' => array(
					'url'      => home_url( '/' ),
					'response' => array(
						'code' => 404,
						'body' => '',
					),
				),
			),
			'home_page_request_empty_body'          => array(
				'mocked_responses' => array(
					'url'      => home_url( '/' ),
					'response' => array(
						'code' => 200,
						'body' => '',
					),
				),
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
