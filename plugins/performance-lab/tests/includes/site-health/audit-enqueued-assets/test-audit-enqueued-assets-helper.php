<?php
/**
 * Tests for audit-enqueued-assets helper file.
 *
 * @package performance-lab
 * @group audit-enqueued-assets
 */

class Test_Audit_Enqueued_Assets_Helper extends WP_UnitTestCase {

	/**
	 * Tests perflab_aea_get_total_enqueued_scripts() no transient saved.
	 */
	public function test_perflab_aea_get_total_enqueued_scripts_no_transient(): void {
		$total_enqueued_scripts = perflab_aea_get_total_enqueued_scripts();
		$this->assertFalse( $total_enqueued_scripts );
	}

	/**
	 * Tests perflab_aea_get_total_enqueued_scripts().
	 */
	public function test_perflab_aea_get_total_enqueued_scripts(): void {
		$total_enqueued_styles = perflab_aea_get_total_enqueued_styles();
		$this->assertFalse( $total_enqueued_styles );

		Audit_Assets_Transients_Set::set_script_transient_with_data( 5 );
		$total_enqueued_scripts = perflab_aea_get_total_enqueued_scripts();
		$this->assertIsInt( $total_enqueued_scripts );
		$this->assertEquals( 5, $total_enqueued_scripts );
	}

	/**
	 * Tests perflab_aea_get_total_size_bytes_enqueued_scripts().
	 */
	public function test_perflab_aea_get_total_size_bytes_enqueued_scripts(): void {
		$size_enqueued_scripts = perflab_aea_get_total_size_bytes_enqueued_scripts();
		$this->assertFalse( $size_enqueued_scripts );

		Audit_Assets_Transients_Set::set_script_transient_with_data( 5 );
		$total_enqueued_scripts = perflab_aea_get_total_size_bytes_enqueued_scripts();
		$this->assertEquals( 5000, $total_enqueued_scripts );
	}

	/**
	 * Tests perflab_aea_get_total_enqueued_styles() with transient saved.
	 */
	public function test_perflab_aea_get_total_enqueued_styles(): void {
		$total_enqueued_styles = perflab_aea_get_total_enqueued_styles();
		$this->assertEquals( 0, $total_enqueued_styles );

		Audit_Assets_Transients_Set::set_style_transient_with_data( 5 );
		$total_enqueued_styles = perflab_aea_get_total_enqueued_styles();
		$this->assertIsInt( $total_enqueued_styles );
		$this->assertEquals( 5, $total_enqueued_styles );
	}

	/**
	 * Tests perflab_aea_get_total_size_bytes_enqueued_styles().
	 */
	public function test_perflab_aea_get_total_size_bytes_enqueued_styles(): void {
		$size_enqueued_scripts = perflab_aea_get_total_size_bytes_enqueued_styles();
		$this->assertFalse( $size_enqueued_scripts );

		Audit_Assets_Transients_Set::set_style_transient_with_data( 5 );
		$total_enqueued_styles = perflab_aea_get_total_size_bytes_enqueued_styles();
		$this->assertEquals( 5000, $total_enqueued_styles );
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
		$this->assertNull( perflab_aea_get_asset_size( 'https://example.com/script.js' ) );

		$this->mock_request(
			'https://example.com/script.js',
			array(
				'response' => array( 'code' => 404 ),
				'body'     => 'Not Found',
			)
		);
		$this->assertNull( perflab_aea_get_asset_size( 'https://example.com/script.js' ) );

		$this->mock_request(
			'https://example.com/script.js',
			array(
				'response' => array( 'code' => 200 ),
				'body'     => '',
			)
		);
		$this->assertNull( perflab_aea_get_asset_size( 'https://example.com/script.js' ) );

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
		unset( $_SERVER['HTTP_AUTHORIZATION'] );
		unset( $_SERVER['PHP_AUTH_USER'] );
		unset( $_SERVER['PHP_AUTH_PW'] );

		$headers = perflab_aea_copy_basic_auth_headers( array() );
		$this->assertArrayNotHasKey( 'Authorization', $headers );

		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic token123';
		$headers                       = perflab_aea_copy_basic_auth_headers( array() );
		$this->assertArrayHasKey( 'Authorization', $headers );
		$this->assertEquals( 'Basic token123', $headers['Authorization'] );

		unset( $_SERVER['HTTP_AUTHORIZATION'] );
		$_SERVER['PHP_AUTH_USER'] = 'user';
		$_SERVER['PHP_AUTH_PW']   = 'pass';
		$headers                  = perflab_aea_copy_basic_auth_headers( array() );
		$this->assertArrayHasKey( 'Authorization', $headers );
		$this->assertEquals( 'Basic ' . base64_encode( 'user:pass' ), $headers['Authorization'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- base64_encode() is used here to encode the credentials for verifying forwarding of basic auth headers.
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
