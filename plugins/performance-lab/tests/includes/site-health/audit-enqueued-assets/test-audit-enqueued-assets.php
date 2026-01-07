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

		wp_enqueue_script( 'script1', 'https://first.example.com', array(), null );
		wp_enqueue_script( 'script2', '/wp-includes/example2.js', array(), null );
		wp_enqueue_script( 'script3', 'https://third.example.com', array(), null );
		wp_dequeue_script( 'script3' );
		wp_enqueue_script( 'script-async', 'https://async-script.example.com', array(), null, true );
		wp_enqueue_script( 'script-defer', 'https://defer-script.example.com', array(), null, true );
		wp_enqueue_script( 'type-noscript', 'https://non-javascript.example.com', array(), null );
		wp_enqueue_script( 'no-src', 'https://no-src.example.com', array(), null );
		wp_enqueue_script( 'boolean-src', 'https://boolean-src.example.com', array(), null );
		wp_enqueue_script( 'empty-src', 'https://empty-src.example.com', array(), null );
		wp_enqueue_script( 'whitespace-src', 'https://whitespace-src.example.com', array(), null );
		wp_enqueue_script_module( 'module1', 'https://module1.example.com', array(), null );

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
				} elseif ( 'boolean-src-js' === $attributes['id'] ) {
					$attributes['src'] = true;
				} elseif ( 'empty-src-js' === $attributes['id'] ) {
					$attributes['src'] = '';
				} elseif ( 'whitespace-src-js' === $attributes['id'] ) {
					$attributes['src'] = '   ';
				}
				return $attributes;
			}
		);

		// Avoid deprecation warning due to related change in WordPress 6.4.
		remove_action( 'wp_print_styles', 'print_emoji_styles' );

		Audit_Assets_Mock_Assets::add_mock_responses(
			array(
				array(
					'url'      => 'https://first.example.com',
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

		$this->assertSame(
			array(
				'https://first.example.com',
				'/wp-includes/example2.js',
			),
			array_map(
				static function ( $src ) {
					return str_replace( home_url( '/' ), '/', $src );
				},
				wp_list_pluck( $assets['scripts'], 'src' )
			)
		);
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

		wp_enqueue_style( 'style1', 'https://first.example.com', array(), null );
		wp_enqueue_style( 'style2', '/wp-includes/example2.css', array(), null );
		wp_enqueue_style( 'style3', 'https://third.example.com', array(), null );
		wp_dequeue_style( 'style3' );
		wp_enqueue_style( 'style-print', 'https://print-style.example.com', array(), null, 'print' );

		// The href for the following styles is mutated via the style_loader_tag filter below.
		wp_enqueue_style( 'style-no-href', 'https://style-no-href.example.com', array(), null );
		wp_enqueue_style( 'style-boolean-href', 'https://style-boolean-href.example.com', array(), null );
		wp_enqueue_style( 'style-empty-href', 'https://style-empty-href.example.com', array(), null );
		wp_enqueue_style( 'style-whitespace-href', 'https://style-whitespace-href.example.com', array(), null );

		// Filter to remove href attribute from a specific style handle.
		add_filter(
			'style_loader_tag',
			function ( $tag, $handle ) {
				$create_processor_state = function ( string $html ): WP_HTML_Tag_Processor {
					$processor = new WP_HTML_Tag_Processor( $html );
					$this->assertTrue( $processor->next_tag( 'LINK' ), 'Expected a LINK to be present.' );
					$this->assertSame( 'stylesheet', $processor->get_attribute( 'rel' ), 'Expected LINK to be a stylesheet.' );
					return $processor;
				};
				if ( 'style-no-href' === $handle ) {
					$processor = $create_processor_state( $tag );
					$this->assertTrue( $processor->remove_attribute( 'href' ) );
					$tag = $processor->get_updated_html();
				} elseif ( 'style-boolean-href' === $handle ) {
					$processor = $create_processor_state( $tag );
					$processor->set_attribute( 'href', true );
					$tag = $processor->get_updated_html();
				} elseif ( 'style-empty-href' === $handle ) {
					$processor = $create_processor_state( $tag );
					$this->assertTrue( $processor->set_attribute( 'href', '' ) );
					$tag = $processor->get_updated_html();
				} elseif ( 'style-whitespace-href' === $handle ) {
					// Note: The HTML Tag Processor cannot be used here because attempting to set an invalid URL to the href will be rejected.
					$tag = str_replace(
						'https://style-whitespace-href.example.com',
						'   ',
						$tag,
						$count
					);
					$this->assertSame( 1, $count, 'Expected string to be replaced.' );
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
					'url'      => 'https://first.example.com',
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

		$this->assertSame(
			array(
				'https://first.example.com',
				'/wp-includes/example2.css',
			),
			array_map(
				static function ( $src ) {
					return str_replace( home_url( '/' ), '/', $src );
				},
				wp_list_pluck( $assets['styles'], 'src' )
			)
		);
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
