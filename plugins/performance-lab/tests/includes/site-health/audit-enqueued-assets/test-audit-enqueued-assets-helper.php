<?php
/**
 * Tests for audit-enqueued-assets helper file.
 *
 * @package performance-lab
 * @group audit-enqueued-assets
 */

class Test_Audit_Enqueued_Assets_Helper extends WP_UnitTestCase {

	/**
	 * Tests perflab_aea_enqueued_blocking_assets_test
	 *
	 * @covers ::perflab_aea_enqueued_blocking_assets_test
	 */
	public function test_perflab_aea_enqueued_blocking_assets_test(): void {
		$this->markTestIncomplete();
	}

	// Note: perflab_aea_enqueued_blocking_scripts() is tested in Test_Audit_Enqueued_Assets.
	// Note: perflab_aea_enqueued_blocking_styles() is tested in Test_Audit_Enqueued_Assets.


	/**
	 * Tests perflab_aea_blocking_assets_retrieval_failure().
	 *
	 * @covers ::perflab_aea_blocking_assets_retrieval_failure
	 */
	public function test_perflab_aea_blocking_assets_retrieval_failure(): void {
		$this->markTestIncomplete();
	}

	/**
	 * Tests perflab_aea_get_total_enqueued_scripts() no transient saved.
	 *
	 * @covers ::perflab_aea_get_total_enqueued_scripts
	 */
	public function test_perflab_aea_get_total_enqueued_scripts_no_transient(): void {
		$this->assertFalse( perflab_aea_get_total_enqueued_scripts() );
	}

	/**
	 * Tests perflab_aea_get_total_enqueued_styles() no transient saved.
	 *
	 * @covers ::perflab_aea_get_total_enqueued_styles
	 */
	public function test_perflab_aea_get_total_enqueued_styles_no_transient(): void {
		$this->assertFalse( perflab_aea_get_total_enqueued_styles() );
	}

	/**
	 * Tests perflab_aea_get_total_enqueued_scripts() with transient saved..
	 *
	 * @covers ::perflab_aea_get_total_enqueued_scripts
	 */
	public function test_perflab_aea_get_total_enqueued_scripts(): void {
		$this->assertFalse( perflab_aea_get_total_enqueued_scripts() );
		Audit_Assets_Transients_Set::set_script_transient_with_data( 5 );
		$this->assertSame( 5, perflab_aea_get_total_enqueued_scripts() );
	}

	/**
	 * Tests perflab_aea_get_total_enqueued_styles() with transient saved.
	 *
	 * @covers ::perflab_aea_get_total_enqueued_styles
	 */
	public function test_perflab_aea_get_total_enqueued_styles(): void {
		$this->assertFalse( perflab_aea_get_total_enqueued_styles() );
		Audit_Assets_Transients_Set::set_style_transient_with_data( 5 );
		$this->assertSame( 5, perflab_aea_get_total_enqueued_styles() );
	}

	/**
	 * Tests perflab_aea_get_total_size_bytes_enqueued_scripts().
	 *
	 * @covers ::perflab_aea_get_total_size_bytes_enqueued_scripts
	 */
	public function test_perflab_aea_get_total_size_bytes_enqueued_scripts(): void {
		$this->assertFalse( perflab_aea_get_total_size_bytes_enqueued_scripts() );

		Audit_Assets_Transients_Set::set_script_transient_with_data( 5 );
		$this->assertSame( 5000, perflab_aea_get_total_size_bytes_enqueued_scripts() );
	}

	/**
	 * Tests perflab_aea_get_total_size_bytes_enqueued_styles().
	 */
	public function test_perflab_aea_get_total_size_bytes_enqueued_styles(): void {
		$this->assertFalse( perflab_aea_get_total_size_bytes_enqueued_styles() );

		Audit_Assets_Transients_Set::set_style_transient_with_data( 5 );
		$this->assertEquals( 5000, perflab_aea_get_total_size_bytes_enqueued_styles() );
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
	 * Tests perflab_aea_generate_blocking_assets_table().
	 *
	 * @covers ::perflab_aea_generate_blocking_assets_table
	 */
	public function test_perflab_aea_generate_blocking_assets_table(): void {
		$this->markTestIncomplete();
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
}
