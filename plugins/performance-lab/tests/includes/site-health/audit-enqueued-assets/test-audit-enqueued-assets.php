<?php
/**
 * Tests for audit-enqueued-assets check.
 *
 * @package performance-lab
 * @group audit-enqueued-assets
 */

class Test_Audit_Enqueued_Assets extends WP_UnitTestCase {

	/**
	 * Mocked responses for HTTP requests.
	 *
	 * @var array<string, mixed>
	 */
	private $mocked_responses = array();

	/**
	 * Tests perflab_aea_audit_enqueued_scripts() when blocking scripts are present.
	 *
	 * @covers ::perflab_aea_audit_blocking_assets
	 */
	public function test_perflab_aea_audit_enqueued_scripts_blocking_scripts_are_present(): void {
		/**
		 * Prepare scenario for test.
		 */
		$this->current_user_can_view_site_health_checks_cap();

		Audit_Assets_Mock_Requests::mock_assets( 'scripts', 3 );
		Audit_Assets_Mock_Requests::mock_requests();

		$result = perflab_aea_audit_blocking_assets();
		$this->assertArrayHasKey( 'assets', $result );
		$this->assertArrayHasKey( 'scripts', $result['assets'] );
		$this->assertEquals( 3, count( $result['assets']['scripts'] ) );
		$this->assertEqualSets(
			array(
				array(
					'src'   => home_url( '/script.js' ),
					'size'  => 1000,
					'error' => null,
				),
				array(
					'src'   => home_url( '/script.js' ),
					'size'  => 1000,
					'error' => null,
				),
				array(
					'src'   => home_url( '/script.js' ),
					'size'  => 1000,
					'error' => null,
				),
			),
			$result['assets']['scripts']
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
		$result = perflab_aea_audit_blocking_assets();
		$this->assertIsArray( $result['assets'] );
		$assets = $result['assets'];
		$this->assertArrayHasKey( 'scripts', $assets );
		$this->assertNotEmpty( $assets['scripts'] );

		$external_script = array_filter(
			$assets['scripts'],
			static function ( $item ) {
				return 'https://example1.com' === $item['src'];
			}
		);
		$this->assertEquals( 1, count( $external_script ) );

		$internal_script = array_filter(
			$assets['scripts'],
			static function ( $item ) {
				return home_url( '/wp-includes/example2.js' ) === $item['src'];
			}
		);
		$this->assertEquals( 1, count( $internal_script ) );

		$async_script = array_filter(
			$assets['scripts'],
			static function ( $item ) {
				return 'https://async-script.com' === $item['src'];
			}
		);
		$this->assertEmpty( $async_script );

		$defer_script = array_filter(
			$assets['scripts'],
			static function ( $item ) {
				return 'https://defer-script.com' === $item['src'];
			}
		);
		$this->assertEmpty( $defer_script );

		$noscript_script = array_filter(
			$assets['scripts'],
			static function ( $item ) {
				return 'https://non-javascript.com' === $item['src'];
			}
		);
		$this->assertEmpty( $noscript_script );
	}

	/**
	 * Tests perflab_aea_audit_enqueued_styles() with no transient.
	 * Enqueued styles ( not belonging to core /wp-includes/ ) will be saved in transient.
	 */
	public function test_perflab_aea_audit_enqueued_styles(): void {
		/**
		 * Prepare scenario for test.
		 */
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
		$result = perflab_aea_audit_blocking_assets();
		$this->assertIsArray( $result['assets'] );
		$assets = $result['assets'];
		$this->assertArrayHasKey( 'styles', $assets );
		$this->assertNotEmpty( $assets['styles'] );

		$external_style = array_filter(
			$assets['styles'],
			static function ( $item ) {
				return 'https://example1.com' === $item['src'];
			}
		);
		$this->assertEquals( 1, count( $external_style ) );

		$internal_style = array_filter(
			$assets['styles'],
			static function ( $item ) {
				return home_url( '/wp-includes/example2.css' ) === $item['src'];
			}
		);
		$this->assertEquals( 1, count( $internal_style ) );

		$print_style = array_filter(
			$assets['styles'],
			static function ( $item ) {
				return 'https://print-style.com' === $item['src'];
			}
		);
		$this->assertEmpty( $print_style );

		$no_href_style = array_filter(
			$assets['styles'],
			static function ( $item ) {
				return 'https://no-href-style.com' === $item['src'];
			}
		);
		$this->assertEmpty( $no_href_style );
	}

	/**
	 * Make sure perflab_aea_add_enqueued_assets_test adds the right information.
	 *
	 * @covers ::perflab_aea_add_enqueued_assets_test
	 */
	public function test_perflab_aea_add_enqueued_assets_test(): void {
		$initial_tests = array(
			'async' => array(
				'initial' => array(
					'label' => 'Label',
					'test'  => 'test',
				),
			),
		);

		$expected          = $initial_tests;
		$expected['async'] = array_merge(
			$expected['async'],
			Site_Health_Mock_Responses::return_added_test_info_site_health()['async']
		);

		$this->assertEqualSets(
			$expected,
			perflab_aea_add_enqueued_assets_test( $initial_tests )
		);
	}

	/**
	 * Tests perflab_aea_invalidate_cache_transients() functionality.
	 *
	 * Now that usage of transients is removed this test ensures that old transients are cleared.
	 *
	 * @covers ::perflab_aea_invalidate_cache_transients
	 */
	public function test_perflab_aea_invalidate_cache_transients(): void {
		set_transient( 'aea_enqueued_front_page_scripts', array() );
		set_transient( 'aea_enqueued_front_page_styles', array() );

		perflab_aea_invalidate_cache_transients();
		$this->assertFalse( get_transient( 'aea_enqueued_front_page_scripts' ) );
		$this->assertFalse( get_transient( 'aea_enqueued_front_page_styles' ) );
	}

	/**
	 * Tests perflab_aea_audit_blocking_assets functionality when the home request fails.
	 *
	 * @covers ::perflab_aea_audit_blocking_assets
	 * @dataProvider data_perflab_aea_audit_blocking_assets_home_request_failure
	 *
	 * @param array<string|mixed> $mocked_response The mocked response to simulate the HTTP request.
	 */
	public function test_perflab_aea_audit_blocking_assets_home_request_failure( array $mocked_response ): void {
		$this->current_user_can_view_site_health_checks_cap();
		$this->add_mock_responses( array( $mocked_response ) );
		$this->mock_request();

		// Avoid deprecation warning due to related change in WordPress 6.4.
		remove_action( 'wp_print_styles', 'print_emoji_styles' );

		$result = perflab_aea_audit_blocking_assets();
		$this->assertSameSets(
			array(
				'response' => $mocked_response['response'],
				'assets'   => array(
					'scripts' => array(),
					'styles'  => array(),
				),
			),
			is_wp_error( $result['response'] ) ? $result : array(
				'response' => $result['response']['response'],
				'assets'   => $result['assets'],
			)
		);
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
	 * Adds view_site_health_checks capability to current user.
	 */
	public function current_user_can_view_site_health_checks_cap(): void {
		$current_user = wp_get_current_user();
		$current_user->add_cap( 'view_site_health_checks' );
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
				$url = remove_query_arg( 'cache_bust', $url );

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
