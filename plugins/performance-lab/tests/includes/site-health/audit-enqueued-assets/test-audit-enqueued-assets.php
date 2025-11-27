<?php
/**
 * Tests for audit-enqueued-assets check.
 *
 * @package performance-lab
 * @group audit-enqueued-assets
 */

class Test_Audit_Enqueued_Assets extends WP_UnitTestCase {

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

		Audit_Assets_Mock_Assets::clear_mocked();
		Audit_Assets_Mock_Assets::mock_assets( 'scripts', 3 );
		Audit_Assets_Mock_Assets::mock_requests();

		$result = perflab_aea_audit_blocking_assets();
		$this->assertArrayHasKey( 'assets', $result );
		$this->assertArrayHasKey( 'scripts', $result['assets'] );
		$this->assertIsArray( $result['assets']['scripts'] );
		$this->assertEquals( 3, count( $result['assets']['scripts'] ) );
		foreach ( $result['assets']['scripts'] as $script ) {
			$this->assertArrayHasKey( 'src', $script );
			$this->assertArrayHasKey( 'size', $script );
			$this->assertArrayHasKey( 'error', $script );
		}
	}

	/**
	 * Tests perflab_aea_audit_enqueued_scripts() with blocking scripts.
	 *
	 * @covers ::perflab_aea_audit_blocking_assets
	 */
	public function test_perflab_aea_audit_enqueued_scripts(): void {
		/**
		 * Prepare scenario for test.
		 */
		$this->current_user_can_view_site_health_checks_cap();
		Audit_Assets_Mock_Assets::clear_mocked();

		wp_enqueue_script( 'script1', 'https://example1.com', array(), null );
		wp_enqueue_script( 'script2', '/wp-includes/example2.js', array(), null );
		wp_enqueue_script( 'script3', 'https://example3.com', array(), null );
		wp_dequeue_script( 'script3' );
		wp_enqueue_script( 'script-async', 'https://async-script.com', array(), null, true );
		wp_enqueue_script( 'script-defer', 'https://defer-script.com', array(), null, true );
		wp_enqueue_script( 'type-noscript', 'https://non-javascript.com', array(), null );
		wp_enqueue_script( 'no-src', 'no-src', array(), null );
		wp_enqueue_script_module( 'module1', 'https://module1.com', array(), null );

		add_filter(
			'wp_script_attributes',
			static function ( $attributes ) {
				if ( 'script-async-js' === $attributes['id'] ) {
					$attributes['async'] = true;
				} elseif ( 'script-defer-js' === $attributes['id'] ) {
					$attributes['defer'] = true;
				} elseif ( 'type-noscript-js' === $attributes['id'] ) {
					$attributes['type'] = 'noscript';
				} elseif ( 'no-src-js' === $attributes['id'] ) {
					unset( $attributes['src'] );
				}
				return $attributes;
			}
		);

		// Avoid deprecation warning due to related change in WordPress 6.4.
		remove_action( 'wp_print_styles', 'print_emoji_styles' );

		Audit_Assets_Mock_Assets::add_mock_responses(
			array(
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
		Audit_Assets_Mock_Assets::mock_requests();
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

		$module_script = array_filter(
			$assets['scripts'],
			static function ( $item ) {
				return 'https://module1.com' === $item['src'];
			}
		);
		$this->assertEmpty( $module_script );
	}

	/**
	 * Tests perflab_aea_audit_enqueued_styles() with blocking styles.
	 *
	 * @covers ::perflab_aea_audit_blocking_assets
	 */
	public function test_perflab_aea_audit_enqueued_styles(): void {
		/**
		 * Prepare scenario for test.
		 */
		$this->current_user_can_view_site_health_checks_cap();
		Audit_Assets_Mock_Assets::clear_mocked();

		wp_enqueue_style( 'style1', 'https://example1.com', array(), null );
		wp_enqueue_style( 'style2', '/wp-includes/example2.css', array(), null );
		wp_enqueue_style( 'style3', 'https://example3.com', array(), null );
		wp_dequeue_style( 'style3' );
		wp_enqueue_style( 'style-print', 'https://print-style.com', array(), null, 'print' );
		wp_enqueue_style( 'style-no-href', 'https://no-href-style.com', array(), null );
		/**
		 * Enqueue style with empty href
		 *
		 * @see https://github.com/WordPress/performance/issues/2278
		 */
		wp_enqueue_style( 'style-empty-href', 'https://empty-href-style.com', array(), null );

		// Filter to remove href attribute from a specific style handle.
		add_filter(
			'style_loader_tag',
			static function ( $tag, $handle ) {
				if ( 'style-no-href' === $handle ) {
					$tag = str_replace( 'href=\'https://no-href-style.com\'', ' ', $tag );
				}

				if ( 'style-empty-href' === $handle ) {
					$tag = str_replace(
						'href=\'https://empty-href-style.com\'',
						"href=''",
						$tag
					);
				}
				return $tag;
			},
			10,
			2
		);

		// Avoid deprecation warning due to related change in WordPress 6.4.
		remove_action( 'wp_print_styles', 'print_emoji_styles' );

		Audit_Assets_Mock_Assets::add_mock_responses(
			array(
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

		Audit_Assets_Mock_Assets::mock_requests();
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

		$empty_href_style = array_filter(
			$assets['styles'],
			static function ( $item ) {
				return 'https://empty-href-style.com' === $item['src'];
			}
		);
		$this->assertEmpty( $empty_href_style );
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
	 * Tests perflab_aea_audit_blocking_assets functionality when the home request fails.
	 *
	 * @covers ::perflab_aea_audit_blocking_assets
	 * @dataProvider data_perflab_aea_audit_blocking_assets_home_request_failure
	 *
	 * @param array<string|mixed> $mocked_response The mocked response to simulate the HTTP request.
	 */
	public function test_perflab_aea_audit_blocking_assets_home_request_failure( array $mocked_response ): void {
		$this->current_user_can_view_site_health_checks_cap();
		Audit_Assets_Mock_Assets::clear_mocked();
		Audit_Assets_Mock_Assets::add_mock_responses( array( $mocked_response ) );
		Audit_Assets_Mock_Assets::mock_requests( array( $mocked_response ) );

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
	 * Tests that hooks are set correctly.
	 *
	 * @covers ::perflab_aea_add_enqueued_assets_test
	 */
	public function test_hooks_are_set(): void {
		$this->assertSame( 10, has_filter( 'site_status_tests', 'perflab_aea_add_enqueued_assets_test' ) );
		$this->assertSame( 10, has_action( 'wp_ajax_health-check-enqueued-blocking-assets-test', 'perflab_aea_enqueued_ajax_blocking_assets_test' ) );
	}

	/**
	 * Adds view_site_health_checks capability to current user.
	 */
	public function current_user_can_view_site_health_checks_cap(): void {
		$current_user = wp_get_current_user();
		$current_user->add_cap( 'view_site_health_checks' );
	}
}
