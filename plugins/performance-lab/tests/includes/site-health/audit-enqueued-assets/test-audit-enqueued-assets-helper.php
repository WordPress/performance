<?php
/**
 * Tests for audit-enqueued-assets helper file.
 *
 * @package performance-lab
 * @group audit-enqueued-assets
 */

class Test_Audit_Enqueued_Assets_Helper extends WP_UnitTestCase {

	const WARNING_SCRIPTS_THRESHOLD = 31;

	const WARNING_STYLES_THRESHOLD = 11;

	/**
	 * Tests perflab_aea_enqueued_blocking_assets_test
	 *
	 * @covers ::perflab_aea_enqueued_blocking_assets_test
	 */
	public function test_perflab_aea_enqueued_blocking_assets_test_no_transient(): void {
		$this->assertSame(
			array( 'omitted' => true ),
			perflab_aea_enqueued_blocking_assets_test()
		);
	}

	/**
	 * Tests perflab_aea_enqueued_blocking_assets_test
	 *
	 * @covers ::perflab_aea_enqueued_blocking_assets_test
	 * @covers ::perflab_aea_generate_blocking_assets_table
	 */
	public function test_perflab_aea_enqueued_blocking_assets_test_good_js_and_css(): void {
		Audit_Assets_Transients_Set::set_assets_transient_with_data( 'scripts', 1 );
		Audit_Assets_Transients_Set::set_assets_transient_with_data( 'styles', 1 );

		$test = perflab_aea_enqueued_blocking_assets_test();
		$this->assertSameSets(
			array( 'label', 'status', 'badge', 'description', 'actions', 'test' ),
			array_keys( $test )
		);
		$this->assertSame( 'good', $test['status'] );

		$processor = new WP_HTML_Tag_Processor( $test['description'] );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'TABLE' ) ) );

		$processor = new WP_HTML_Tag_Processor( $test['actions'] );
		$this->assertFalse( $processor->next_tag( array( 'tag_name' => 'A' ) ) );
	}

	/**
	 * Tests perflab_aea_enqueued_blocking_assets_test
	 *
	 * @covers ::perflab_aea_enqueued_blocking_assets_test
	 * @covers ::perflab_aea_generate_blocking_assets_table
	 */
	public function test_perflab_aea_enqueued_blocking_assets_test_bad_js_and_css(): void {
		Audit_Assets_Transients_Set::set_assets_transient_with_data( 'scripts', self::WARNING_SCRIPTS_THRESHOLD );
		Audit_Assets_Transients_Set::set_assets_transient_with_data( 'styles', self::WARNING_STYLES_THRESHOLD );

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
	 */
	public function test_perflab_aea_enqueued_blocking_assets_test_bad_js_but_good_css(): void {
		Audit_Assets_Transients_Set::set_assets_transient_with_data( 'scripts', self::WARNING_SCRIPTS_THRESHOLD );
		Audit_Assets_Transients_Set::set_assets_transient_with_data( 'styles', 1 );

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
	 */
	public function test_perflab_aea_enqueued_blocking_assets_test_good_js_but_bad_css(): void {
		Audit_Assets_Transients_Set::set_assets_transient_with_data( 'scripts', 1 );
		Audit_Assets_Transients_Set::set_assets_transient_with_data( 'styles', self::WARNING_STYLES_THRESHOLD );

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
		set_transient( 'aea_blocking_assets_response', new WP_Error( 'something_bad', 'Oh no!!!' ) );

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
		set_transient(
			'aea_blocking_assets_response',
			array(
				'response' => array(
					'code'    => 404,
					'message' => 'Not Found',
				),
				'body'     => 'Where art thou?',
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
		set_transient(
			'aea_blocking_assets_response',
			array(
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'body'     => '',
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
	 */
	public function test_perflab_aea_enqueued_blocking_assets_test_but_one_script_is_404(): void {
		Audit_Assets_Transients_Set::set_assets_transient_with_data( 'scripts', 3, 1 );
		Audit_Assets_Transients_Set::set_assets_transient_with_data( 'styles', 2 );
		$test = perflab_aea_enqueued_blocking_assets_test();

		$this->assertSameSets(
			array( 'label', 'status', 'badge', 'description', 'actions', 'test' ),
			array_keys( $test )
		);
		$this->assertSame( 'recommended', $test['status'] );
	}

	/**
	 * Tests perflab_aea_enqueued_blocking_assets_test
	 *
	 * @covers ::perflab_aea_enqueued_blocking_assets_test
	 */
	public function test_perflab_aea_enqueued_blocking_assets_test_but_one_stylet_is_404(): void {
		Audit_Assets_Transients_Set::set_assets_transient_with_data( 'scripts', 3 );
		Audit_Assets_Transients_Set::set_assets_transient_with_data( 'styles', 2, 1 );
		$test = perflab_aea_enqueued_blocking_assets_test();

		$this->assertSameSets(
			array( 'label', 'status', 'badge', 'description', 'actions', 'test' ),
			array_keys( $test )
		);
		$this->assertSame( 'recommended', $test['status'] );
	}

	/**
	 * Test perflab_aea_enqueued_blocking_scripts() no transient saved.
	 *
	 * @covers ::perflab_aea_enqueued_blocking_scripts
	 */
	public function test_perflab_aea_enqueued_js_assets_test_no_transient(): void {
		$this->assertNull( perflab_aea_enqueued_blocking_scripts() );
	}

	/**
	 * Test perflab_aea_enqueued_blocking_scripts() with data in transient ( less than WARNING_SCRIPTS_threshold ).
	 *
	 * @covers ::perflab_aea_enqueued_blocking_scripts
	 */
	public function test_perflab_aea_enqueued_js_assets_test_with_assets_less_than_threshold(): void {
		Audit_Assets_Transients_Set::set_assets_transient_with_data( 'scripts', 1 );
		$mocked_data = $this->mock_data_perflab_aea_enqueued_js_assets_test_callback( 1 );
		$this->assertEqualSets( $mocked_data, perflab_aea_enqueued_blocking_scripts() );
	}

	/**
	 * Test perflab_aea_enqueued_blocking_scripts() with data in transient ( more than WARNING_SCRIPTS_threshold ).
	 *
	 * @covers ::perflab_aea_enqueued_blocking_scripts
	 */
	public function test_perflab_aea_enqueued_js_assets_test_with_assets_more_than_threshold(): void {
		Audit_Assets_Transients_Set::set_assets_transient_with_data( 'scripts', self::WARNING_SCRIPTS_THRESHOLD );
		$mocked_data = $this->mock_data_perflab_aea_enqueued_js_assets_test_callback( self::WARNING_SCRIPTS_THRESHOLD );
		$this->assertEqualSets( $mocked_data, perflab_aea_enqueued_blocking_scripts() );
	}

	/**
	 * Test perflab_aea_enqueued_blocking_styles() no transient saved.
	 *
	 * @covers ::perflab_aea_enqueued_blocking_styles
	 */
	public function test_perflab_aea_enqueued_css_assets_test_no_transient(): void {
		$this->assertNull( perflab_aea_enqueued_blocking_styles() );
	}

	/**
	 * Test perflab_aea_enqueued_blocking_styles() with data in transient ( less than WARNING_STYLES_threshold ).
	 *
	 * @covers ::perflab_aea_enqueued_blocking_styles
	 */
	public function test_perflab_aea_enqueued_css_assets_test_with_assets_less_than_threshold(): void {
		Audit_Assets_Transients_Set::set_assets_transient_with_data( 'styles', 1 );
		$mocked_data = $this->mock_data_perflab_aea_enqueued_css_assets_test_callback( 1 );
		$this->assertEqualSets( $mocked_data, perflab_aea_enqueued_blocking_styles() );
	}

	/**
	 * Test perflab_aea_enqueued_blocking_styles() with data in transient ( more than WARNING_STYLES_threshold ).
	 *
	 * @covers ::perflab_aea_enqueued_blocking_styles
	 */
	public function test_aea_enqueued_cdd_assets_test_with_assets_more_than_threshold(): void {
		Audit_Assets_Transients_Set::set_assets_transient_with_data( 'styles', self::WARNING_STYLES_THRESHOLD );
		$mocked_data = $this->mock_data_perflab_aea_enqueued_css_assets_test_callback( self::WARNING_STYLES_THRESHOLD );
		$this->assertEqualSets( $mocked_data, perflab_aea_enqueued_blocking_styles() );
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
	public function test_perflab_aea_blocking_assets_retrieval_failure_404(): void {
		$response = array(
			'response' => array(
				'code'    => 404,
				'message' => 'Not Found',
			),
			'body'     => 'You are so lost',
		);
		$result   = perflab_aea_blocking_assets_retrieval_failure( $response );
		$this->assertIsArray( $result );
		$this->assertSame( 'recommended', $result['status'] );
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
	 * Tests perflab_aea_get_total_enqueued_assets() no transient saved.
	 *
	 * @covers ::perflab_aea_get_total_enqueued_assets
	 */
	public function test_perflab_aea_get_total_enqueued_assets_no_transient(): void {
		$this->assertNull( perflab_aea_get_total_enqueued_assets( 'scripts' ) );
		$this->assertNull( perflab_aea_get_total_enqueued_assets( 'styles' ) );
	}

	/**
	 * Tests perflab_aea_get_total_enqueued_assets( 'scripts' ) with transient saved..
	 *
	 * @covers ::perflab_aea_get_total_enqueued_assets
	 */
	public function test_perflab_aea_get_total_enqueued_scripts(): void {
		$this->assertNull( perflab_aea_get_total_enqueued_assets( 'scripts' ) );
		Audit_Assets_Transients_Set::set_assets_transient_with_data( 'scripts', 5 );
		$this->assertSame( 5, perflab_aea_get_total_enqueued_assets( 'scripts' ) );
	}

	/**
	 * Tests perflab_aea_get_total_enqueued_assets( 'styles' ) with transient saved.
	 *
	 * @covers ::perflab_aea_get_total_enqueued_assets
	 */
	public function test_perflab_aea_get_total_enqueued_styles(): void {
		$this->assertNull( perflab_aea_get_total_enqueued_assets( 'styles' ) );
		Audit_Assets_Transients_Set::set_assets_transient_with_data( 'styles', 5 );
		$this->assertSame( 5, perflab_aea_get_total_enqueued_assets( 'styles' ) );
	}

	/**
	 * Tests perflab_aea_get_total_size_bytes_enqueued_assets( 'scripts' ).
	 *
	 * @covers ::perflab_aea_get_total_size_bytes_enqueued_assets
	 */
	public function test_perflab_aea_get_total_size_bytes_enqueued_scripts(): void {
		$this->assertNull( perflab_aea_get_total_size_bytes_enqueued_assets( 'scripts' ) );

		Audit_Assets_Transients_Set::set_assets_transient_with_data( 'scripts', 5 );
		$this->assertSame( 5000, perflab_aea_get_total_size_bytes_enqueued_assets( 'scripts' ) );
	}

	/**
	 * Tests perflab_aea_get_total_size_bytes_enqueued_assets( 'styles' ).
	 *
	 * @covers ::perflab_aea_get_total_size_bytes_enqueued_assets
	 */
	public function test_perflab_aea_get_total_size_bytes_enqueued_styles(): void {
		$this->assertNull( perflab_aea_get_total_size_bytes_enqueued_assets( 'styles' ) );

		Audit_Assets_Transients_Set::set_assets_transient_with_data( 'styles', 5 );
		$this->assertEquals( 5000, perflab_aea_get_total_size_bytes_enqueued_assets( 'styles' ) );
	}

	/**
	 * Tests perflab_aea_get_asset_size() with various scenarios.
	 *
	 * @covers ::perflab_aea_get_asset_size
	 */
	public function test_perflab_aea_get_asset_size(): void {
		$this->mock_request(
			'https://example.com/script.js',
			new WP_Error(
				'http_error',
				'Mocked HTTP error for testing.'
			)
		);
		$this->assertWPError( perflab_aea_get_asset_size( 'https://example.com/script.js' ) );

		$this->mock_request(
			'https://example.com/script.js',
			array(
				'response' => array(
					'code'    => 404,
					'message' => 'Not Found',
				),
				'body'     => 'Not Found',
			)
		);
		$this->assertWPError( perflab_aea_get_asset_size( 'https://example.com/script.js' ) );

		$this->mock_request(
			'https://example.com/script.js',
			array(
				'response' => array( 'code' => 200 ),
				'body'     => '',
			)
		);
		$this->assertEquals( 0, perflab_aea_get_asset_size( 'https://example.com/script.js' ) );

		$this->mock_request(
			'https://example.com/script.js',
			array(
				'response' => array( 'code' => 200 ),
				'body'     => str_repeat( 'a', 1000 ),
			)
		);
		$size = perflab_aea_get_asset_size( 'https://example.com/script.js' );
		$this->assertEquals( 1000, $size );
	}

	/**
	 * Tests perflab_aea_copy_basic_auth_headers() with various scenarios.
	 *
	 * @covers ::perflab_aea_copy_basic_auth_headers
	 */
	public function test_perflab_aea_copy_basic_auth_headers(): void {
		unset( $_SERVER['PHP_AUTH_USER'] );
		unset( $_SERVER['PHP_AUTH_PW'] );

		$headers = perflab_aea_copy_basic_auth_headers( array() );
		$this->assertArrayNotHasKey( 'Authorization', $headers );

		$_SERVER['PHP_AUTH_USER'] = 'user';
		$_SERVER['PHP_AUTH_PW']   = 'pass';
		$headers                  = perflab_aea_copy_basic_auth_headers( array() );
		$this->assertArrayHasKey( 'Authorization', $headers );
		$this->assertEquals( 'Basic ' . base64_encode( 'user:pass' ), $headers['Authorization'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- base64_encode() is used here to encode the credentials for verifying forwarding of basic auth headers.
	}

	/**
	 * Tests perflab_aea_generate_blocking_assets_table() without anything.
	 *
	 * @covers ::perflab_aea_generate_blocking_assets_table
	 */
	public function test_perflab_aea_generate_blocking_assets_table_empty(): void {
		delete_transient( 'aea_blocking_assets' );
		$this->assertSame( '', perflab_aea_generate_blocking_assets_table() );
	}

	/**
	 * Tests perflab_aea_generate_blocking_assets_table() with scripts.
	 *
	 * @covers ::perflab_aea_generate_blocking_assets_table
	 */
	public function test_perflab_aea_generate_blocking_assets_table_scripts(): void {
		Audit_Assets_Transients_Set::set_assets_transient_with_data( 'scripts', 5 );
		$table     = perflab_aea_generate_blocking_assets_table();
		$processor = new WP_HTML_Tag_Processor( $table );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'TABLE' ) ) );
	}

	/**
	 * Tests perflab_aea_generate_blocking_assets_table() with styles.
	 *
	 * @covers ::perflab_aea_generate_blocking_assets_table
	 */
	public function test_perflab_aea_generate_blocking_assets_table_css(): void {
		Audit_Assets_Transients_Set::set_assets_transient_with_data( 'styles', 5 );
		$table     = perflab_aea_generate_blocking_assets_table();
		$processor = new WP_HTML_Tag_Processor( $table );
		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'TABLE' ) ) );
	}

	/**
	 * Mocks HTTP requests for tests.
	 *
	 * @param string                       $resource_url The URL to mock.
	 * @param array<string|mixed>|WP_Error $response The response to return for the mocked request.
	 */
	public function mock_request( string $resource_url, $response ): void {
		remove_all_filters( 'pre_http_request' );
		add_filter(
			'pre_http_request',
			static function ( $preempt, $parsed_args, $url ) use ( $resource_url, $response ) {
				if ( $url === $resource_url ) {
					if ( is_wp_error( $response ) ) {
						return $response;
					}

					return array(
						'response' => $response['response'],
						'body'     => $response['body'] ?? '',
					);
				}
				return $preempt;
			},
			10,
			3
		);
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
}
