<?php
/**
 * Tests for effective caching headers health check.
 *
 * @package performance-lab
 * @group effective-asset-cache-headers
 */

class Test_Effective_Asset_Cache_Headers extends WP_UnitTestCase {

	/**
	 * Holds mocked response headers for different test scenarios.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	protected $mocked_responses = array();

	/**
	 * Setup each test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Clear any filters or mocks.
		remove_all_filters( 'pre_http_request' );

		// Add the filter to mock HTTP requests.
		add_filter( 'pre_http_request', array( $this, 'mock_http_requests' ), 10, 3 );
	}

	/**
	 * Test that the effective caching headers test is added to the site health tests.
	 *
	 * @covers ::perflab_effective_asset_cache_headers_add_test
	 */
	public function test_perflab_effective_asset_cache_headers_add_test(): void {
		$tests = array(
			'direct' => array(),
		);

		$tests = perflab_effective_asset_cache_headers_add_test( $tests );

		$this->assertArrayHasKey( 'effective_asset_cache_headers', $tests['direct'] );
		$this->assertEquals( 'Effective Caching Headers', $tests['direct']['effective_asset_cache_headers']['label'] );
		$this->assertEquals( 'perflab_effective_asset_cache_headers_assets_test', $tests['direct']['effective_asset_cache_headers']['test'] );
	}

	/**
	 * Test that the effective caching headers test is attached to the site status tests.
	 *
	 * @covers ::perflab_effective_asset_cache_headers_add_test
	 */
	public function test_perflab_effective_asset_cache_headers_add_test_is_attached_to_site_status_tests(): void {
		$this->assertNotFalse( has_filter( 'site_status_tests', 'perflab_effective_asset_cache_headers_add_test' ) );
	}

	/**
	 * Test that when all assets have valid effective caching headers, the status is "good".
	 *
	 * @covers ::perflab_effective_asset_cache_headers_assets_test
	 * @covers ::perflab_effective_asset_cache_headers_check_assets
	 * @covers ::perflab_effective_asset_cache_headers_check_headers
	 */
	public function test_all_assets_valid_effective_cache_headers(): void {
		// Mock responses: all assets have a max-age > 1 year (threshold).
		$this->mocked_responses = array(
			includes_url( 'js/wp-embed.min.js' )     => $this->build_response( 200, array( 'cache-control' => 'max-age=' . ( YEAR_IN_SECONDS + 1000 ) ) ),
			includes_url( 'css/buttons.min.css' )    => $this->build_response( 200, array( 'cache-control' => 'max-age=' . ( YEAR_IN_SECONDS + 500 ) ) ),
			includes_url( 'fonts/dashicons.woff2' )  => $this->build_response( 200, array( 'expires' => gmdate( 'D, d M Y H:i:s', time() + YEAR_IN_SECONDS + 1000 ) . ' GMT' ) ),
			includes_url( 'images/media/video.png' ) => $this->build_response( 200, array( 'cache-control' => 'max-age=' . ( YEAR_IN_SECONDS + 2000 ) ) ),
		);

		$result = perflab_effective_asset_cache_headers_assets_test();
		$this->assertEquals( 'good', $result['status'] );
		$this->assertEmpty( $result['actions'] );
	}

	/**
	 * Test that when an asset has no effective caching headers but has conditional caching (ETag/Last-Modified), status is 'recommended'.
	 *
	 * @covers ::perflab_effective_asset_cache_headers_assets_test
	 * @covers ::perflab_effective_asset_cache_headers_check_assets
	 * @covers ::perflab_effective_asset_cache_headers_check_headers
	 * @covers ::perflab_effective_asset_cache_headers_try_conditional_request
	 * @covers ::perflab_effective_asset_cache_headers_get_status_table
	 */
	public function test_assets_conditionally_cached(): void {
		// For conditional caching scenario, setting etag/last-modified headers.
		$this->mocked_responses = array(
			includes_url( 'js/wp-embed.min.js' )     => $this->build_response( 200, array( 'cache-control' => 'max-age=' . ( YEAR_IN_SECONDS + 1000 ) ) ),
			includes_url( 'css/buttons.min.css' )    => $this->build_response( 200, array( 'etag' => '"123456789"' ) ),
			includes_url( 'fonts/dashicons.woff2' )  => $this->build_response( 200, array( 'last-modified' => gmdate( 'D, d M Y H:i:s', time() - 1000 ) . ' GMT' ) ),
			includes_url( 'images/media/video.png' ) => $this->build_response(
				200,
				array(
					'etag'          => '"123456789"',
					'last-modified' => gmdate( 'D, d M Y H:i:s', time() - 1000 ) . ' GMT',
				)
			),
			'conditional_304'                        => $this->build_response( 304 ),
		);

		$result = perflab_effective_asset_cache_headers_assets_test();
		$this->assertEquals( 'recommended', $result['status'] );
		$this->assertNotEmpty( $result['actions'] );
	}

	/**
	 * Test that ETag/Last-Modified is used for conditional requests.
	 *
	 * @dataProvider data_provider_conditional_headers
	 * @covers ::perflab_effective_asset_cache_headers_try_conditional_request
	 *
	 * @param string                        $url      The URL to test.
	 * @param array<string, string>         $headers  The headers to send.
	 * @param array<string, mixed>|WP_Error $response The response to return.
	 * @param bool                          $expected The expected result.
	 */
	public function test_try_conditional_request_function( string $url, array $headers, $response, bool $expected ): void {
		$this->mocked_responses = array(
			$url => $response,
		);

		$result = perflab_effective_asset_cache_headers_try_conditional_request( $url, $headers );

		$this->assertEquals( $expected, $result );
	}

	/**
	 * Data provider for test_try_conditional_request_function.
	 *
	 * @return array<array<mixed>> Data provider.
	 */
	public function data_provider_conditional_headers(): array {
		return array(
			array(
				includes_url( 'js/wp-embed.min.js' ),
				array( 'If-None-Match' => '"123456789"' ),
				$this->build_response( 304 ),
				true,
			),
			array(
				includes_url( 'css/buttons.min.css' ),
				array( 'If-Modified-Since' => gmdate( 'D, d M Y H:i:s', time() - 1000 ) . ' GMT' ),
				$this->build_response( 304 ),
				true,
			),
			array(
				includes_url( 'fonts/dashicons.woff2' ),
				array( 'If-None-Match' => '"123456789"' ),
				$this->build_response( 200 ),
				false,
			),
			array(
				includes_url( 'images/media/video.png' ),
				array( 'If-Modified-Since' => gmdate( 'D, d M Y H:i:s', time() - 1000 ) . ' GMT' ),
				$this->build_response( 200 ),
				false,
			),
			array(
				includes_url( 'images/media/video.png' ),
				array(),
				new WP_Error( 'http_request_failed', 'HTTP request failed' ),
				false,
			),
		);
	}

	/**
	 * Test that different status messages are returned based on the test results.
	 *
	 * @covers ::perflab_effective_asset_cache_headers_check_assets
	 * @covers ::perflab_effective_asset_cache_headers_check_headers
	 * @covers ::perflab_effective_asset_cache_headers_try_conditional_request
	 */
	public function test_status_messages(): void {
		$this->mocked_responses = array(
			includes_url( 'js/wp-embed.min.js' )     => $this->build_response( 200, array( 'cache-control' => 'max-age=' . ( YEAR_IN_SECONDS - 1000 ) ) ),
			includes_url( 'css/buttons.min.css' )    => $this->build_response( 200, array( 'expires' => gmdate( 'D, d M Y H:i:s', time() + YEAR_IN_SECONDS - 1000 ) . ' GMT' ) ),
			includes_url( 'images/blank.gif' )       => $this->build_response(
				200,
				array(
					'cache-control' => 'max-age=' . ( YEAR_IN_SECONDS - 1000 ),
					'expires'       => gmdate( 'D, d M Y H:i:s', time() - 1000 ) . ' GMT',
				)
			),
			'conditional_304'                        => $this->build_response( 304 ),
			includes_url( 'fonts/dashicons.woff2' )  => $this->build_response( 200, array( 'etag' => '"123456789"' ) ),
			includes_url( 'images/media/video.png' ) => $this->build_response( 200, array() ),
			includes_url( 'images/media/video.svg' ) => new WP_Error( 'http_request_failed', 'HTTP request failed' ),
			includes_url( 'images/media/code.png' )  => array(),
		);

		$result = perflab_effective_asset_cache_headers_check_assets(
			array(
				includes_url( 'js/wp-embed.min.js' ),
				includes_url( 'css/buttons.min.css' ),
				includes_url( 'images/blank.gif' ),
				includes_url( 'fonts/dashicons.woff2' ),
				includes_url( 'images/media/video.png' ),
				includes_url( 'images/media/video.svg' ),
				includes_url( 'images/media/code.png' ),
			)
		);

		$this->assertEquals( 'recommended', $result['final_status'] );
		$this->assertStringContainsString( 'max-age below threshold (actual:', $result['details'][0]['reason'] );
		$this->assertStringContainsString( 'expires below threshold (actual:', $result['details'][1]['reason'] );
		$this->assertStringContainsString( 'max-age below threshold (actual:', $result['details'][2]['reason'] );
		$this->assertEquals( 'No effective caching headers but conditionally cached', $result['details'][3]['reason'] );
		$this->assertEquals( 'No effective caching headers and no conditional caching', $result['details'][4]['reason'] );
		$this->assertEquals( 'Could not retrieve headers', $result['details'][5]['reason'] );
		$this->assertEquals( 'No valid headers retrieved', $result['details'][6]['reason'] );
	}

	/**
	 * Test that the filter `perflab_effective_asset_cache_headers_assets_to_check` and `perflab_effective_asset_cache_headers_expiration_threshold` are working as expected.
	 *
	 * @covers ::perflab_effective_asset_cache_headers_check_assets
	 * @covers ::perflab_effective_asset_cache_headers_check_headers
	 */
	public function test_filters(): void {
		add_filter(
			'perflab_effective_asset_cache_headers_assets_to_check',
			static function ( $assets ) {
				$assets[] = includes_url( 'images/blank.gif' );
				return $assets;
			}
		);

		add_filter(
			'perflab_effective_asset_cache_headers_expiration_threshold',
			static function () {
				return 1000;
			}
		);

		$this->mocked_responses = array(
			includes_url( 'js/wp-embed.min.js' )     => $this->build_response( 200, array( 'cache-control' => 'max-age=' . 1500 ) ),
			includes_url( 'css/buttons.min.css' )    => $this->build_response( 200, array( 'cache-control' => 'max-age=' . 500 ) ),
			includes_url( 'fonts/dashicons.woff2' )  => $this->build_response( 200, array( 'expires' => gmdate( 'D, d M Y H:i:s', time() + 1500 ) . ' GMT' ) ),
			includes_url( 'images/media/video.png' ) => $this->build_response( 200, array( 'expires' => gmdate( 'D, d M Y H:i:s', time() + 500 ) . ' GMT' ) ),
			includes_url( 'images/blank.gif' )       => $this->build_response( 200, array( 'cache-control' => 'max-age=' . ( 500 ) ) ),
		);

		$result = perflab_effective_asset_cache_headers_check_assets(
			array(
				includes_url( 'js/wp-embed.min.js' ),
				includes_url( 'css/buttons.min.css' ),
				includes_url( 'fonts/dashicons.woff2' ),
				includes_url( 'images/media/video.png' ),
				includes_url( 'images/blank.gif' ),
			)
		);

		$this->assertEquals( 'recommended', $result['final_status'] );
		$this->assertStringContainsString( 'max-age below threshold (actual:', $result['details'][0]['reason'] );
		$this->assertStringContainsString( 'expires below threshold (actual:', $result['details'][1]['reason'] );
		$this->assertStringContainsString( 'max-age below threshold (actual:', $result['details'][2]['reason'] );
	}

	/**
	 * Test that when no assets are passed, the status is "good".
	 *
	 * @covers ::perflab_effective_asset_cache_headers_check_assets
	 */
	public function test_when_no_assets(): void {
		$this->mocked_responses = array();

		$result = perflab_effective_asset_cache_headers_check_assets( array() );

		$this->assertEquals( 'good', $result['final_status'] );
		$this->assertEmpty( $result['details'] );
	}

	/**
	 * Mock HTTP requests for assets to simulate different responses.
	 *
	 * @param bool                 $response A preemptive return value of an HTTP request. Default false.
	 * @param array<string, mixed> $args     Request arguments.
	 * @param string               $url      The request URL.
	 * @return array<string, mixed>|WP_Error Mocked response.
	 */
	public function mock_http_requests( bool $response, array $args, string $url ) {
		// If conditional headers used in second request, simulate a 304 response.
		if ( isset( $this->mocked_responses['conditional_304'] ) && ( isset( $args['headers']['If-None-Match'] ) || isset( $args['headers']['If-Modified-Since'] ) ) ) {
			return $this->mocked_responses['conditional_304'];
		}

		if ( isset( $this->mocked_responses[ $url ] ) ) {
			return $this->mocked_responses[ $url ];
		}

		// If no specific mock set, default to a generic success with no caching.
		return $this->build_response( 200 );
	}

	/**
	 * Helper method to build a mock HTTP response.
	 *
	 * @param int                       $status_code HTTP status code.
	 * @param array<string, string|int> $headers     HTTP headers.
	 * @return array{response: array{code: int, message: string}, headers: WpOrg\Requests\Utility\CaseInsensitiveDictionary} Mock response.
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
