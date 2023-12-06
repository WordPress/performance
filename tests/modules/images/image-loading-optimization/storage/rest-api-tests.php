<?php
/**
 * Tests for image-loading-optimization module storage/rest-api.php.
 *
 * @package performance-lab
 * @group   image-loading-optimization
 */

class Image_Loading_Optimization_Storage_REST_API_Tests extends WP_UnitTestCase {

	/**
	 * @var string
	 */
	const ROUTE = '/' . ILO_REST_API_NAMESPACE . ILO_URL_METRICS_ROUTE;

	/**
	 * Test ilo_register_endpoint().
	 *
	 * @test
	 * @covers ::ilo_register_endpoint
	 */
	public function test_ilo_register_endpoint_hooked() {
		$this->assertSame( 10, has_action( 'rest_api_init', 'ilo_register_endpoint' ) );
	}

	/**
	 * Test good params.
	 *
	 * @test
	 * @covers ::ilo_register_endpoint
	 * @covers ::ilo_handle_rest_request
	 */
	public function test_rest_request_good_params() {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$valid_params = $this->get_valid_params();
		$request->set_body_params( $valid_params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertIsInt( $data['post_id'] );

		$post = ilo_get_url_metrics_post( $valid_params['slug'] );
		$this->assertSame( $post->ID, $data['post_id'] );

		$url_metrics = ilo_parse_stored_url_metrics( $post );
		$this->assertCount( 1, $url_metrics );
		foreach ( array( 'viewport', 'elements' ) as $key ) {
			$this->assertSame( $valid_params[ $key ], $url_metrics[0][ $key ] );
		}
		$this->assertArrayHasKey( 'timestamp', $url_metrics[0] );
	}

	/**
	 * Data provider for test_rest_request_bad_params.
	 *
	 * @return array
	 */
	public function data_provider_invalid_params(): array {
		$valid_element = $this->get_valid_params()['elements'][0];

		return array_map(
			function ( $params ) {
				return array(
					'params' => array_merge( $this->get_valid_params(), $params ),
				);
			},
			array(
				'bad_url' => array(
					'url' => 'bad://url',
				),
				'other_origin_url' => array(
					'url' => 'https://bogus.example.com/'
				),
				'bad_slug' => array(
					'slug' => '<script>document.write("evil")</script>'
				),
				'bad_nonce' => array(
					'nonce' => 'not even a hash'
				),
				'invalid_nonce' => array(
					'nonce' => ilo_get_url_metrics_storage_nonce( ilo_get_url_metrics_slug( array( 'different' => 'query vars' ) ) )
				),
				'invalid_viewport_type' => array(
					'viewport' => '640x480'
				),
				'invalid_viewport_values' => array(
					'viewport' => array( 'breadth' => 100, 'depth' => 200 )
				),
				'invalid_elements_type' => array(
					'elements' => 'bad'
				),
				'invalid_elements_prop_is_lcp' => array(
					'elements' => array(
						array_merge(
							$valid_element,
							array(
								'isLCP' => 'totally!',
							)
						)
					),
				),
				'invalid_elements_prop_xpath' => array(
					'elements' => array(
						array_merge(
							$valid_element,
							array(
								'xpath' => 'html > body img',
							)
						)
					),
				),
				'invalid_elements_prop_intersection_ratio' => array(
					'elements' => array(
						array_merge(
							$valid_element,
							array(
								'intersectionRatio' => -1,
							)
						)
					),
				),
			)
		);
	}

	/**
	 * Test bad params.
	 *
	 * @test
	 * @covers ::ilo_register_endpoint
	 * @covers ::ilo_handle_rest_request
	 * @dataProvider data_provider_invalid_params
	 */
	public function test_rest_request_bad_params( array $params ) {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
	}

	/**
	 * Test REST API request when metric storage is locked.
	 *
	 * @test
	 * @covers ::ilo_register_endpoint
	 * @covers ::ilo_handle_rest_request
	 */
	public function test_rest_request_locked() {
		ilo_set_url_metric_storage_lock();

		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_body_params( $this->get_valid_params() );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'url_metric_storage_locked', $response->get_data()['code'] );
	}


	/**
	 * Test sending viewport data that isn't needed.
	 *
	 * @test
	 * @covers ::ilo_register_endpoint
	 * @covers ::ilo_handle_rest_request
	 */
	public function test_rest_request_breakpoint_not_needed() {
		add_filter( 'ilo_url_metric_storage_lock_ttl', '__return_zero' );

		// First fully populate the sample for a given breakpoint.
		$sample_size     = ilo_get_url_metrics_breakpoint_sample_size();
		$viewport_widths = array_merge( ilo_get_breakpoint_max_widths(), array( 1000 ) );
		foreach ( $viewport_widths as $breakpoint_width ) {
			for ( $i = 0; $i < $sample_size; $i++ ) {
				$valid_params = $this->get_valid_params();
				$valid_params['viewport']['width'] = $breakpoint_width;
				$request = new WP_REST_Request( 'POST', self::ROUTE );
				$request->set_body_params( $valid_params );
				$response = rest_get_server()->dispatch( $request );
				$this->assertSame( 200, $response->get_status() );
			}
		}

		// The next request with the same sample size will be rejected.
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_body_params( $this->get_valid_params() );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Gets valid params.
	 *
	 * @return array
	 */
	private function get_valid_params(): array {
		$slug = ilo_get_url_metrics_slug( array() );
		return array_merge(
			array(
				'url'   => home_url( '/' ),
				'slug'  => $slug,
				'nonce' => ilo_get_url_metrics_storage_nonce( $slug ),
			),
			$this->get_sample_validated_url_metric()
		);
	}

	/**
	 * Gets sample validated URL metric data.
	 *
	 * @return array
	 */
	private function get_sample_validated_url_metric(): array {
		return array(
			'viewport' => array(
				'width'  => 480,
				'height' => 640,
			),
			'elements' => array(
				array(
					'isLCP'              => true,
					'isLCPCandidate'     => true,
					'xpath'              => '/*[0][self::HTML]/*[1][self::BODY]/*[0][self::DIV]/*[1][self::MAIN]/*[0][self::DIV]/*[0][self::FIGURE]/*[0][self::IMG]',
					'intersectionRatio'  => 1,
				),
			),
		);
	}
}
