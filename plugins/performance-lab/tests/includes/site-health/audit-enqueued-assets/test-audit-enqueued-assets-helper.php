<?php
/**
 * Tests for audit-enqueued-assets helper file.
 *
 * @package performance-lab
 * @group audit-enqueued-assets
 */

class Test_Audit_Enqueued_Assets_Helper extends WP_Ajax_UnitTestCase {

	const WARNING_SCRIPTS_THRESHOLD = 31;

	const WARNING_STYLES_THRESHOLD = 11;

	/**
	 * Set up.
	 */
	public function set_up(): void {
		parent::set_up();
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
	}

	/**
	 * Tear down.
	 */
	public function tear_down(): void {
		unset( $_SERVER['PHP_AUTH_USER'] );
		unset( $_SERVER['PHP_AUTH_PW'] );
		parent::tear_down();
	}

	/**
	 * Tests perflab_aea_enqueued_blocking_assets_test
	 *
	 * @covers ::perflab_aea_enqueued_blocking_assets_test
	 * @covers ::perflab_aea_generate_blocking_assets_table
	 * @covers ::perflab_aea_enqueued_blocking_scripts
	 * @covers ::perflab_aea_enqueued_blocking_styles
	 */
	public function test_perflab_aea_enqueued_blocking_assets_test_good_js_and_css(): void {
		Audit_Assets_Mock_Assets::mock_assets( 'scripts', 1 );
		Audit_Assets_Mock_Assets::mock_assets( 'styles', 1 );
		Audit_Assets_Mock_Assets::mock_requests();

		$test = perflab_aea_enqueued_blocking_assets_test();
		$this->assertSameSets(
			array( 'label', 'status', 'badge', 'description', 'actions', 'test' ),
			array_keys( $test )
		);
		$this->assertSame( 'good', $test['status'] );

		$processor = new WP_HTML_Tag_Processor( $test['description'] );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'TABLE' ) ) );
		$this->assertStringContainsString( 'OK', $test['description'] );
		$this->assertStringContainsString( '1,000&nbsp;B', $test['description'] );
		$this->assertStringNotContainsString( 'N/A', $test['description'] );
		$this->assertStringNotContainsString( 'Not found', $test['description'] );

		$processor = new WP_HTML_Tag_Processor( $test['actions'] );
		$this->assertFalse( $processor->next_tag( array( 'tag_name' => 'A' ) ) );
	}

	/**
	 * Tests perflab_aea_enqueued_blocking_assets_test
	 *
	 * @covers ::perflab_aea_enqueued_blocking_assets_test
	 * @covers ::perflab_aea_generate_blocking_assets_table
	 * @covers ::perflab_aea_enqueued_blocking_scripts
	 * @covers ::perflab_aea_enqueued_blocking_styles
	 */
	public function test_perflab_aea_enqueued_blocking_assets_test_bad_js_and_css(): void {
		Audit_Assets_Mock_Assets::mock_assets( 'scripts', self::WARNING_SCRIPTS_THRESHOLD );
		Audit_Assets_Mock_Assets::mock_assets( 'styles', self::WARNING_STYLES_THRESHOLD );
		Audit_Assets_Mock_Assets::mock_requests();

		$test = perflab_aea_enqueued_blocking_assets_test();
		$this->assertSameSets(
			array( 'label', 'status', 'badge', 'description', 'actions', 'test' ),
			array_keys( $test )
		);
		$this->assertSame( 'recommended', $test['status'] );

		$processor = new WP_HTML_Tag_Processor( $test['description'] );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'TABLE' ) ) );

		$processor = new WP_HTML_Tag_Processor( $test['actions'] );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'A' ) ) );
	}

	/**
	 * Tests perflab_aea_enqueued_blocking_assets_test
	 *
	 * @covers ::perflab_aea_enqueued_blocking_assets_test
	 * @covers ::perflab_aea_generate_blocking_assets_table
	 * @covers ::perflab_aea_enqueued_blocking_scripts
	 * @covers ::perflab_aea_enqueued_blocking_styles
	 */
	public function test_perflab_aea_enqueued_blocking_assets_test_bad_js_but_good_css(): void {
		Audit_Assets_Mock_Assets::mock_assets( 'scripts', self::WARNING_SCRIPTS_THRESHOLD );
		Audit_Assets_Mock_Assets::mock_assets( 'styles', 1 );
		Audit_Assets_Mock_Assets::mock_requests();

		$test = perflab_aea_enqueued_blocking_assets_test();
		$this->assertSameSets(
			array( 'label', 'status', 'badge', 'description', 'actions', 'test' ),
			array_keys( $test )
		);
		$this->assertSame( 'recommended', $test['status'] );

		$processor = new WP_HTML_Tag_Processor( $test['description'] );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'TABLE' ) ) );

		$processor = new WP_HTML_Tag_Processor( $test['actions'] );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'A' ) ) );
	}

	/**
	 * Tests perflab_aea_enqueued_blocking_assets_test
	 *
	 * @covers ::perflab_aea_enqueued_blocking_assets_test
	 * @covers ::perflab_aea_generate_blocking_assets_table
	 * @covers ::perflab_aea_enqueued_blocking_scripts
	 * @covers ::perflab_aea_enqueued_blocking_styles
	 */
	public function test_perflab_aea_enqueued_blocking_assets_test_good_js_but_bad_css(): void {
		Audit_Assets_Mock_Assets::mock_assets( 'scripts', 1 );
		Audit_Assets_Mock_Assets::mock_assets( 'styles', self::WARNING_STYLES_THRESHOLD );
		Audit_Assets_Mock_Assets::mock_requests();

		$test = perflab_aea_enqueued_blocking_assets_test();
		$this->assertSameSets(
			array( 'label', 'status', 'badge', 'description', 'actions', 'test' ),
			array_keys( $test )
		);
		$this->assertSame( 'recommended', $test['status'] );

		$processor = new WP_HTML_Tag_Processor( $test['description'] );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'TABLE' ) ) );

		$processor = new WP_HTML_Tag_Processor( $test['actions'] );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'A' ) ) );
	}

	/**
	 * Tests perflab_aea_enqueued_blocking_assets_test
	 *
	 * @covers ::perflab_aea_enqueued_blocking_assets_test
	 * @covers ::perflab_aea_blocking_assets_retrieval_failure
	 */
	public function test_perflab_aea_enqueued_blocking_assets_test_but_response_is_wp_error(): void {
		Audit_Assets_Mock_Assets::mock_requests(
			array(
				array(
					'url'      => home_url( '/' ),
					'response' => new WP_Error( 'something_bad', 'Oh no!!!' ),
				),
			)
		);

		$test = perflab_aea_enqueued_blocking_assets_test();
		$this->assertSameSets(
			array( 'label', 'status', 'badge', 'description', 'actions', 'test' ),
			array_keys( $test )
		);
		$this->assertSame( 'recommended', $test['status'] );

		$processor = new WP_HTML_Tag_Processor( $test['description'] );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'BLOCKQUOTE' ) ) );
	}

	/**
	 * Tests perflab_aea_enqueued_blocking_assets_test
	 *
	 * @covers ::perflab_aea_enqueued_blocking_assets_test
	 * @covers ::perflab_aea_blocking_assets_retrieval_failure
	 */
	public function test_perflab_aea_enqueued_blocking_assets_test_but_response_is_http_error(): void {
		Audit_Assets_Mock_Assets::mock_requests(
			array(
				array(
					'url'      => home_url( '/' ),
					'response' => array(
						'code'    => 404,
						'message' => 'Not Found',
						'body'    => 'Where art thou?',
					),
				),
			)
		);

		$test = perflab_aea_enqueued_blocking_assets_test();
		$this->assertSameSets(
			array( 'label', 'status', 'badge', 'description', 'actions', 'test' ),
			array_keys( $test )
		);
		$this->assertSame( 'recommended', $test['status'] );

		$processor = new WP_HTML_Tag_Processor( $test['description'] );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'DETAILS' ) ) );
	}

	/**
	 * Tests perflab_aea_enqueued_blocking_assets_test
	 *
	 * @covers ::perflab_aea_enqueued_blocking_assets_test
	 * @covers ::perflab_aea_blocking_assets_retrieval_failure
	 */
	public function test_perflab_aea_enqueued_blocking_assets_test_but_response_is_empty_body(): void {
		Audit_Assets_Mock_Assets::mock_requests(
			array(
				array(
					'url'      => home_url( '/' ),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
						'body'    => '',
					),
				),
			)
		);

		$test = perflab_aea_enqueued_blocking_assets_test();
		$this->assertSameSets(
			array( 'label', 'status', 'badge', 'description', 'actions', 'test' ),
			array_keys( $test )
		);
		$this->assertSame( 'recommended', $test['status'] );

		$this->assertStringContainsString( 'body was empty', $test['description'] );
	}

	/**
	 * Tests perflab_aea_enqueued_blocking_assets_test
	 *
	 * @covers ::perflab_aea_enqueued_blocking_assets_test
	 * @covers ::perflab_aea_generate_blocking_assets_table
	 * @covers ::perflab_aea_enqueued_blocking_scripts
	 * @covers ::perflab_aea_enqueued_blocking_styles
	 */
	public function test_perflab_aea_enqueued_blocking_assets_test_but_one_script_is_404(): void {
		Audit_Assets_Mock_Assets::mock_assets( 'scripts', 3, 1 );
		Audit_Assets_Mock_Assets::mock_assets( 'styles', 2 );
		Audit_Assets_Mock_Assets::mock_requests();
		$test = perflab_aea_enqueued_blocking_assets_test();

		$this->assertSameSets(
			array( 'label', 'status', 'badge', 'description', 'actions', 'test' ),
			array_keys( $test )
		);
		$this->assertSame( 'recommended', $test['status'] );

		$this->assertStringContainsString( 'N/A', $test['description'] );
		$this->assertStringContainsString( 'Not found', $test['description'] );
	}

	/**
	 * Tests perflab_aea_enqueued_blocking_assets_test
	 *
	 * @covers ::perflab_aea_enqueued_blocking_assets_test
	 * @covers ::perflab_aea_generate_blocking_assets_table
	 * @covers ::perflab_aea_enqueued_blocking_scripts
	 * @covers ::perflab_aea_enqueued_blocking_styles
	 */
	public function test_perflab_aea_enqueued_blocking_assets_test_but_one_style_is_404(): void {
		Audit_Assets_Mock_Assets::mock_assets( 'scripts', 2 );
		Audit_Assets_Mock_Assets::mock_assets( 'styles', 3, 1 );
		Audit_Assets_Mock_Assets::mock_requests();
		$test = perflab_aea_enqueued_blocking_assets_test();

		$this->assertSameSets(
			array( 'label', 'status', 'badge', 'description', 'actions', 'test' ),
			array_keys( $test )
		);
		$this->assertSame( 'recommended', $test['status'] );

		$this->assertStringContainsString( 'N/A', $test['description'] );
		$this->assertStringContainsString( 'Not found', $test['description'] );
	}

	/**
	 * Tests perflab_aea_enqueued_ajax_blocking_assets_test
	 *
	 * @covers ::perflab_aea_enqueued_ajax_blocking_assets_test
	 */
	public function test_perflab_aea_enqueued_ajax_blocking_assets_test_unauthenticated_without_nonce(): void {
		$this->add_filter_to_mock_front_page_loopback_request();
		$this->assertFalse( current_user_can( 'view_site_health_checks' ) );
		$exception = null;
		try {
			$this->_handleAjax( 'health-check-enqueued-blocking-assets-test' );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( WPAjaxDieStopException::class, $exception );
		$this->assertEquals( '-1', $exception->getMessage() );
		$this->assertSame( '', $this->_last_response );
	}

	/**
	 * Tests perflab_aea_enqueued_ajax_blocking_assets_test
	 *
	 * @covers ::perflab_aea_enqueued_ajax_blocking_assets_test
	 */
	public function test_perflab_aea_enqueued_ajax_blocking_assets_test_unauthenticated_with_nonce(): void {
		$this->add_filter_to_mock_front_page_loopback_request();
		$this->assertFalse( current_user_can( 'view_site_health_checks' ) );
		$_GET['_wpnonce'] = wp_create_nonce( 'health-check-site-status' );
		$exception        = null;
		try {
			$this->_handleAjax( 'health-check-enqueued-blocking-assets-test' );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( WPAjaxDieContinueException::class, $exception );
		$this->assertEquals( '', $exception->getMessage() );
		$response = json_decode( $this->_last_response, true );
		$this->assertFalse( $response['success'] );
	}

	/**
	 * Tests perflab_aea_enqueued_ajax_blocking_assets_test
	 *
	 * @covers ::perflab_aea_enqueued_ajax_blocking_assets_test
	 */
	public function test_perflab_aea_enqueued_ajax_blocking_assets_test_unauthorized(): void {
		$this->add_filter_to_mock_front_page_loopback_request();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertFalse( current_user_can( 'view_site_health_checks' ) );
		$_GET['_wpnonce'] = wp_create_nonce( 'health-check-site-status' );
		$exception        = null;
		try {
			$this->_handleAjax( 'health-check-enqueued-blocking-assets-test' );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( WPAjaxDieContinueException::class, $exception );
		$this->assertEquals( '', $exception->getMessage() );
		$response = json_decode( $this->_last_response, true );
		$this->assertFalse( $response['success'] );
	}

	/**
	 * Tests perflab_aea_enqueued_ajax_blocking_assets_test
	 *
	 * @covers ::perflab_aea_enqueued_ajax_blocking_assets_test
	 */
	public function test_perflab_aea_enqueued_ajax_blocking_assets_test_authorized(): void {
		$this->add_filter_to_mock_front_page_loopback_request();
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );
		grant_super_admin( $admin_id );
		$this->assertTrue( current_user_can( 'view_site_health_checks' ) );
		$_GET['_wpnonce'] = wp_create_nonce( 'health-check-site-status' );
		$exception        = null;
		try {
			$this->_handleAjax( 'health-check-enqueued-blocking-assets-test' );
		} catch ( Exception $e ) {
			$exception = $e;
		}
		$this->assertInstanceOf( WPAjaxDieContinueException::class, $exception );
		$this->assertEquals( '', $exception->getMessage() );
		$response = json_decode( $this->_last_response, true );
		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'data', $response );
		$this->assertSameSets(
			array( 'label', 'status', 'badge', 'description', 'actions', 'test' ),
			array_keys( $response['data'] )
		);
	}

	/**
	 * Test perflab_aea_enqueued_blocking_scripts() with scripts less than WARNING_SCRIPTS_threshold.
	 *
	 * @covers ::perflab_aea_enqueued_blocking_scripts
	 */
	public function test_perflab_aea_enqueued_js_assets_test_with_assets_less_than_threshold(): void {
		$mocked_data = $this->mock_data_perflab_aea_enqueued_js_assets_test_callback( 1 );
		$this->assertEqualSets( $mocked_data, perflab_aea_enqueued_blocking_scripts( Audit_Assets_Mock_Assets::mock_assets( 'scripts', 1 ) ) );
	}

	/**
	 * Test perflab_aea_enqueued_blocking_scripts() with scripts more than WARNING_SCRIPTS_threshold.
	 *
	 * @covers ::perflab_aea_enqueued_blocking_scripts
	 */
	public function test_perflab_aea_enqueued_js_assets_test_with_assets_more_than_threshold(): void {
		$mocked_data = $this->mock_data_perflab_aea_enqueued_js_assets_test_callback( self::WARNING_SCRIPTS_THRESHOLD );
		$this->assertEqualSets( $mocked_data, perflab_aea_enqueued_blocking_scripts( Audit_Assets_Mock_Assets::mock_assets( 'scripts', self::WARNING_SCRIPTS_THRESHOLD ) ) );
	}

	/**
	 * Test perflab_aea_enqueued_blocking_styles() with styles less than WARNING_STYLES_threshold.
	 *
	 * @covers ::perflab_aea_enqueued_blocking_styles
	 */
	public function test_perflab_aea_enqueued_css_assets_test_with_assets_less_than_threshold(): void {
		$mocked_data = $this->mock_data_perflab_aea_enqueued_css_assets_test_callback( 1 );
		$this->assertEqualSets( $mocked_data, perflab_aea_enqueued_blocking_styles( Audit_Assets_Mock_Assets::mock_assets( 'styles', 1 ) ) );
	}

	/**
	 * Test perflab_aea_enqueued_blocking_styles() with styles more than WARNING_STYLES_threshold.
	 *
	 * @covers ::perflab_aea_enqueued_blocking_styles
	 */
	public function test_aea_enqueued_css_assets_test_with_assets_more_than_threshold(): void {
		$mocked_data = $this->mock_data_perflab_aea_enqueued_css_assets_test_callback( self::WARNING_STYLES_THRESHOLD );
		$this->assertEqualSets( $mocked_data, perflab_aea_enqueued_blocking_styles( Audit_Assets_Mock_Assets::mock_assets( 'styles', self::WARNING_STYLES_THRESHOLD ) ) );
	}

	/**
	 * Tests perflab_aea_blocking_assets_retrieval_failure().
	 *
	 * @covers ::perflab_aea_blocking_assets_retrieval_failure
	 */
	public function test_perflab_aea_blocking_assets_retrieval_failure_not(): void {
		$response = array(
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'body'     => 'HEY!!',
		);
		$this->assertNull( perflab_aea_blocking_assets_retrieval_failure( $response ) );
	}

	/**
	 * Tests perflab_aea_blocking_assets_retrieval_failure().
	 *
	 * @covers ::perflab_aea_blocking_assets_retrieval_failure
	 */
	public function test_perflab_aea_blocking_assets_retrieval_failure_404_plain_text(): void {
		$response = array(
			'response' => array(
				'code'    => 404,
				'message' => 'Not Found',
			),
			'headers'  => array(
				'content-type' => 'text/plain',
			),
			'body'     => 'You are so lost',
		);
		$result   = perflab_aea_blocking_assets_retrieval_failure( $response );
		$this->assertIsArray( $result );
		$this->assertSame( 'recommended', $result['status'] );
		$processor = new WP_HTML_Tag_Processor( $result['description'] );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'PRE' ) ) );
	}

	/**
	 * Tests perflab_aea_blocking_assets_retrieval_failure().
	 *
	 * @covers ::perflab_aea_blocking_assets_retrieval_failure
	 */
	public function test_perflab_aea_blocking_assets_retrieval_failure_404_html(): void {
		$response = array(
			'response' => array(
				'code'    => 404,
				'message' => 'Not Found',
			),
			'headers'  => array(
				'content-type' => array( 'text/html' ),
			),
			'body'     => '<html lang="en">Whoops!!</html>',
		);
		$result   = perflab_aea_blocking_assets_retrieval_failure( $response );
		$this->assertIsArray( $result );
		$this->assertSame( 'recommended', $result['status'] );
		$processor = new WP_HTML_Tag_Processor( $result['description'] );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'IFRAME' ) ) );
	}

	/**
	 * Tests perflab_aea_blocking_assets_retrieval_failure().
	 *
	 * @covers ::perflab_aea_blocking_assets_retrieval_failure
	 */
	public function test_perflab_aea_blocking_assets_retrieval_failure_wp_error(): void {
		$result = perflab_aea_blocking_assets_retrieval_failure( new WP_Error( 'something_bad', 'Oh no!!!' ) );
		$this->assertIsArray( $result );
		$this->assertSame( 'recommended', $result['status'] );
	}

	/**
	 * Tests perflab_aea_get_asset_size() with various scenarios.
	 *
	 * @covers ::perflab_aea_get_asset_size
	 */
	public function test_perflab_aea_get_asset_size(): void {
		Audit_Assets_Mock_Assets::clear_mocked();
		Audit_Assets_Mock_Assets::mock_requests(
			array(
				array(
					'url'      => 'https://example.com/script1.js',
					'response' => new WP_Error( 'http_error', 'Mocked HTTP error for testing.' ),
				),
				array(
					'url'      => 'https://example.com/script2.js',
					'response' => array(
						'code'    => 404,
						'message' => 'Not Found',
					),
				),
				array(
					'url'      => 'https://example.com/script3.js',
					'response' => array(
						'code' => 200,
						'body' => '',
					),
				),
				array(
					'url'      => 'https://example.com/script4.js',
					'response' => array(
						'code' => 200,
						'body' => str_repeat( 'A', 1000 ),
					),
				),
			)
		);

		$this->assertWPError( perflab_aea_get_asset_size( 'https://example.com/script1.js' ) );
		$this->assertWPError( perflab_aea_get_asset_size( 'https://example.com/script2.js' ) );
		$zero_size_result = perflab_aea_get_asset_size( 'https://example.com/script3.js' );
		$this->assertWPError( $zero_size_result );
		$this->assertSame( 'zero_size', $zero_size_result->get_error_code() );
		$this->assertEquals( 1000, perflab_aea_get_asset_size( 'https://example.com/script4.js' ) );
	}

	/**
	 * Tests perflab_aea_get_asset_size() with a Cache-Control: no-store response.
	 *
	 * @covers ::perflab_aea_get_asset_size
	 */
	public function test_perflab_aea_get_asset_size_not_cacheable(): void {
		Audit_Assets_Mock_Assets::clear_mocked();
		Audit_Assets_Mock_Assets::mock_requests(
			array(
				array(
					'url'      => 'https://example.com/no-store.js',
					'response' => array(
						'code'    => 200,
						'body'    => str_repeat( 'A', 500 ),
						'headers' => array( 'cache-control' => 'no-store' ),
					),
				),
				array(
					'url'      => 'https://example.com/no-store-with-extras.js',
					'response' => array(
						'code'    => 200,
						'body'    => str_repeat( 'A', 500 ),
						'headers' => array( 'cache-control' => 'no-store, must-revalidate' ),
					),
				),
				array(
					'url'      => 'https://example.com/no-cache.js',
					'response' => array(
						'code'    => 200,
						'body'    => str_repeat( 'A', 500 ),
						'headers' => array( 'cache-control' => 'no-cache' ),
					),
				),
				array(
					'url'      => 'https://example.com/cacheable.js',
					'response' => array(
						'code'    => 200,
						'body'    => str_repeat( 'A', 500 ),
						'headers' => array( 'cache-control' => 'max-age=3600' ),
					),
				),
			)
		);

		$no_store_result = perflab_aea_get_asset_size( 'https://example.com/no-store.js' );
		$this->assertWPError( $no_store_result );
		$this->assertSame( 'not_cacheable', $no_store_result->get_error_code() );

		$no_store_extras_result = perflab_aea_get_asset_size( 'https://example.com/no-store-with-extras.js' );
		$this->assertWPError( $no_store_extras_result );
		$this->assertSame( 'not_cacheable', $no_store_extras_result->get_error_code() );

		// no-cache means revalidate, not no-store — should not be flagged.
		$this->assertEquals( 500, perflab_aea_get_asset_size( 'https://example.com/no-cache.js' ) );

		// Normal cacheable response should return size.
		$this->assertEquals( 500, perflab_aea_get_asset_size( 'https://example.com/cacheable.js' ) );
	}

	/**
	 * Tests perflab_aea_copy_basic_auth_headers() with various scenarios.
	 *
	 * @covers ::perflab_get_http_basic_authorization_headers
	 */
	public function test_perflab_get_http_basic_authorization_headers(): void {
		unset( $_SERVER['PHP_AUTH_USER'] );
		unset( $_SERVER['PHP_AUTH_PW'] );

		$headers = perflab_get_http_basic_authorization_headers();
		$this->assertArrayNotHasKey( 'Authorization', $headers );

		$_SERVER['PHP_AUTH_USER'] = 'user';
		$_SERVER['PHP_AUTH_PW']   = 'pass';
		$headers                  = perflab_get_http_basic_authorization_headers();
		$this->assertArrayHasKey( 'Authorization', $headers );
		$this->assertEquals( 'Basic ' . base64_encode( 'user:pass' ), $headers['Authorization'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- base64_encode() is used here to encode the credentials for verifying forwarding of basic auth headers.
	}

	/**
	 * Tests perflab_aea_generate_blocking_assets_table() without anything.
	 *
	 * @covers ::perflab_aea_generate_blocking_assets_table
	 */
	public function test_perflab_aea_generate_blocking_assets_table_empty(): void {
		$this->assertSame(
			'',
			perflab_aea_generate_blocking_assets_table(
				array(
					'scripts' => array(),
					'styles'  => array(),
				)
			)
		);
	}

	/**
	 * Tests perflab_aea_generate_blocking_assets_table() with scripts.
	 *
	 * @covers ::perflab_aea_generate_blocking_assets_table
	 */
	public function test_perflab_aea_generate_blocking_assets_table_scripts(): void {
		$table     = perflab_aea_generate_blocking_assets_table(
			array_merge(
				array( 'styles' => array() ),
				Audit_Assets_Mock_Assets::mock_assets( 'scripts' )
			)
		);
		$processor = new WP_HTML_Tag_Processor( $table );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'TABLE' ) ) );
	}

	/**
	 * Tests perflab_aea_generate_blocking_assets_table() with styles.
	 *
	 * @covers ::perflab_aea_generate_blocking_assets_table
	 */
	public function test_perflab_aea_generate_blocking_assets_table_css(): void {
		$table     = perflab_aea_generate_blocking_assets_table(
			array_merge(
				array( 'scripts' => array() ),
				Audit_Assets_Mock_Assets::mock_assets( 'styles' )
			)
		);
		$processor = new WP_HTML_Tag_Processor( $table );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'TABLE' ) ) );
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
	 * Add filter to intercept loopback requests.
	 */
	public function add_filter_to_mock_front_page_loopback_request(): void {
		add_filter(
			'pre_http_request',
			static function ( $r, $args, $url ) {
				if ( home_url( '/' ) === remove_query_arg( 'cache_bust', $url ) ) {
					$r = array(
						'response' => array(
							'status'  => 200,
							'message' => 'OK',
						),
						'body'     => '<html lang="en"></html>',
						'headers'  => array(
							'Content-Type' => 'text/html',
						),
					);
				} else {
					$r = array(
						'response' => array(
							'status'  => 503,
							'message' => "Oh no you didn't",
						),
						'body'     => 'NO WAY',
						'headers'  => array(
							'Content-Type' => 'text/plain',
						),
					);
				}
				return $r;
			},
			10,
			3
		);
	}
}
