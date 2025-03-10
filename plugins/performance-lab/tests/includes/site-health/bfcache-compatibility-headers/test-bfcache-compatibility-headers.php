<?php
/**
 * Tests for cache-control headers for bfcache compatibility site health check.
 *
 * @package performance-lab
 * @group bfcache-compatibility-headers
 */

class Test_BFCache_Compatibility_Headers extends WP_UnitTestCase {

	/**
	 * Test that the bfcache compatibility test is added to the site health tests.
	 *
	 * @covers ::perflab_bfcache_compatibility_headers_add_test
	 */
	public function test_perflab_bfcache_compatibility_headers_add_test(): void {
		$tests = array(
			'direct' => array(),
		);

		$tests = perflab_bfcache_compatibility_headers_add_test( $tests );
		$this->assertArrayHasKey( 'perflab_bfcache_compatibility_headers', $tests['direct'] );
		$this->assertEquals( 'Cache-Control headers may prevent fast back/forward navigation', $tests['direct']['perflab_bfcache_compatibility_headers']['label'] );
		$this->assertEquals( 'perflab_bfcache_compatibility_headers_check', $tests['direct']['perflab_bfcache_compatibility_headers']['test'] );
	}

	/**
	 * Test that the bfcache compatibility test is attached to the site status tests.
	 *
	 * @covers ::perflab_bfcache_compatibility_headers_add_test
	 */
	public function test_perflab_bfcache_compatibility_headers_add_test_is_attached(): void {
		$this->assertNotFalse( has_filter( 'site_status_tests', 'perflab_bfcache_compatibility_headers_add_test' ) );
	}

	/**
	 * Test that different Cache-Control headers return the correct bfcache compatibility result.
	 *
	 * @dataProvider data_test_bfcache_compatibility
	 * @covers ::perflab_bfcache_compatibility_headers_check
	 *
	 * @param array<int, mixed>|WP_Error $response The response headers.
	 * @param string                     $expected_status   The expected status.
	 * @param string                     $expected_message  The expected message.
	 */
	public function test_perflab_bfcache_compatibility_headers_check( $response, string $expected_status, string $expected_message ): void {
		$this->mock_http_request( $response, home_url( '/' ) );

		$result = perflab_bfcache_compatibility_headers_check();

		$this->assertEquals( $expected_status, $result['status'] );
		$this->assertStringContainsString( $expected_message, $result['description'] );
	}

	/**
	 * Data provider for bfcache compatibility tests.
	 *
	 * @return array<string, array<int, mixed>> Test data.
	 */
	public function data_test_bfcache_compatibility(): array {
		return array(
			'headers_not_set'    => array(
				$this->build_response( 200, array( 'cache-control' => '' ) ),
				'good',
				'If the <code>Cache-Control</code> page response header includes',
			),
			'no_store'           => array(
				$this->build_response( 200, array( 'cache-control' => 'no-store' ) ),
				'recommended',
				'<p>The <code>Cache-Control</code> response header for an unauthenticated request to the home page includes',
			),
			'no_cache'           => array(
				$this->build_response( 200, array( 'cache-control' => 'no-cache' ) ),
				'good',
				'If the <code>Cache-Control</code> page response header includes',
			),
			'max_age_0'          => array(
				$this->build_response( 200, array( 'cache-control' => 'no-cache' ) ),
				'good',
				'If the <code>Cache-Control</code> page response header includes',
			),
			'max_age_0_no_store' => array(
				$this->build_response( 200, array( 'cache-control' => 'max-age=0, no-store' ) ),
				'recommended',
				'<p>The <code>Cache-Control</code> response header for an unauthenticated request to the home page includes',
			),
			'error'              => array(
				new WP_Error( 'http_request_failed', 'HTTP request failed' ),
				'recommended',
				'The unauthenticated request to check the <code>Cache-Control</code> response header for the home page resulted in an error with code',
			),
		);
	}

	/**
	 * Mock HTTP response for a given URL.
	 *
	 * @param array<string, mixed>|WP_Error $mocked_response The mocked response.
	 * @param non-empty-string              $url             The request URL.
	 */
	public function mock_http_request( $mocked_response, string $url ): void {
		add_filter(
			'pre_http_request',
			static function ( $pre, $args, $request_url ) use ( $url, $mocked_response ) {
				if ( $url === $request_url ) {
					return $mocked_response;
				}
				return $pre;
			},
			10,
			3
		);
	}

	/**
	 * Helper method to build a mock HTTP response.
	 *
	 * @param int                       $status_code HTTP status code.
	 * @param array<string, string|int> $headers     HTTP headers.
	 * @return array{response: array{code: int, message: string}, headers: WpOrg\Requests\Utility\CaseInsensitiveDictionary}
	 */
	protected function build_response( int $status_code = 200, array $headers = array() ): array {
		return array(
			'response' => array(
				'code'    => $status_code,
				'message' => '',
			),
			'headers'  => new WpOrg\Requests\Utility\CaseInsensitiveDictionary( $headers ),
		);
	}
}
