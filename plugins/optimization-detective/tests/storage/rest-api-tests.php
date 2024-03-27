<?php
/**
 * Tests for optimization-detective plugin storage/rest-api.php.
 *
 * @package optimization-detective
 */

class OD_Storage_REST_API_Tests extends WP_UnitTestCase {

	/**
	 * @var string
	 */
	const ROUTE = '/' . OD_REST_API_NAMESPACE . OD_URL_METRICS_ROUTE;

	/**
	 * Test od_register_endpoint().
	 *
	 * @covers ::od_register_endpoint
	 */
	public function test_od_register_endpoint_hooked() {
		$this->assertSame( 10, has_action( 'rest_api_init', 'od_register_endpoint' ) );
	}

	/**
	 * Test good params.
	 *
	 * @covers ::od_register_endpoint
	 * @covers ::od_handle_rest_request
	 */
	public function test_rest_request_good_params() {
		$request      = new WP_REST_Request( 'POST', self::ROUTE );
		$valid_params = $this->get_valid_params();
		$this->assertCount( 0, get_posts( array( 'post_type' => OD_URL_Metrics_Post_Type::SLUG ) ) );
		$request->set_body_params( $valid_params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status(), 'Response: ' . wp_json_encode( $response ) );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );

		$this->assertCount( 1, get_posts( array( 'post_type' => OD_URL_Metrics_Post_Type::SLUG ) ) );
		$post = OD_URL_Metrics_Post_Type::get_post( $valid_params['slug'] );
		$this->assertInstanceOf( WP_Post::class, $post );

		$url_metrics = OD_URL_Metrics_Post_Type::get_url_metrics_from_post( $post );
		$this->assertCount( 1, $url_metrics, 'Expected number of URL metrics stored.' );
		$this->assertSame( $valid_params['elements'], $url_metrics[0]->get_elements() );
		$this->assertSame( $valid_params['viewport']['width'], $url_metrics[0]->get_viewport_width() );
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
				'bad_url'                                  => array(
					'url' => 'bad://url',
				),
				'bad_slug'                                 => array(
					'slug' => '<script>document.write("evil")</script>',
				),
				'bad_nonce'                                => array(
					'nonce' => 'not even a hash',
				),
				'invalid_nonce'                            => array(
					'nonce' => od_get_url_metrics_storage_nonce( od_get_url_metrics_slug( array( 'different' => 'query vars' ) ), home_url( '/' ) ),
				),
				'invalid_viewport_type'                    => array(
					'viewport' => '640x480',
				),
				'invalid_viewport_values'                  => array(
					'viewport' => array(
						'breadth' => 100,
						'depth'   => 200,
					),
				),
				'invalid_elements_type'                    => array(
					'elements' => 'bad',
				),
				'invalid_elements_prop_is_lcp'             => array(
					'elements' => array(
						array_merge(
							$valid_element,
							array(
								'isLCP' => 'totally!',
							)
						),
					),
				),
				'invalid_elements_prop_xpath'              => array(
					'elements' => array(
						array_merge(
							$valid_element,
							array(
								'xpath' => 'html > body img',
							)
						),
					),
				),
				'invalid_elements_prop_intersection_ratio' => array(
					'elements' => array(
						array_merge(
							$valid_element,
							array(
								'intersectionRatio' => - 1,
							)
						),
					),
				),
			)
		);
	}

	/**
	 * Test bad params.
	 *
	 * @covers ::od_register_endpoint
	 * @covers ::od_handle_rest_request
	 *
	 * @dataProvider data_provider_invalid_params
	 */
	public function test_rest_request_bad_params( array $params ) {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 400, $response->get_status(), 'Response: ' . wp_json_encode( $response ) );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'], 'Response: ' . wp_json_encode( $response ) );

		$this->assertNull( OD_URL_Metrics_Post_Type::get_post( $params['slug'] ) );
	}

	/**
	 * Test timestamp ignored.
	 *
	 * @covers ::od_register_endpoint
	 * @covers ::od_handle_rest_request
	 */
	public function test_rest_request_timestamp_ignored() {
		$initial_microtime = microtime( true );

		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$params  = $this->get_valid_params(
			array(
				// Timestamp should cause to be ignored.
				'timestamp' => microtime( true ) - HOUR_IN_SECONDS,
			)
		);
		$request->set_body_params( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Response: ' . wp_json_encode( $response ) );

		$post = OD_URL_Metrics_Post_Type::get_post( $params['slug'] );
		$this->assertInstanceOf( WP_Post::class, $post );

		$url_metrics = OD_URL_Metrics_Post_Type::get_url_metrics_from_post( $post );
		$this->assertCount( 1, $url_metrics );
		$url_metric = $url_metrics[0];
		$this->assertNotEquals( $params['timestamp'], $url_metric->get_timestamp() );
		$this->assertGreaterThanOrEqual( $initial_microtime, $url_metric->get_timestamp() );
	}

	/**
	 * Test REST API request when metric storage is locked.
	 *
	 * @covers ::od_register_endpoint
	 * @covers ::od_handle_rest_request
	 */
	public function test_rest_request_locked() {
		OD_Storage_Lock::set_lock();

		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_body_params( $this->get_valid_params() );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'url_metric_storage_locked', $response->get_data()['code'] );
	}

	/**
	 * Test sending viewport data that isn't needed for any breakpoint.
	 *
	 * @covers ::od_register_endpoint
	 * @covers ::od_handle_rest_request
	 */
	public function test_rest_request_breakpoint_not_needed_for_any_breakpoint() {
		add_filter( 'od_url_metric_storage_lock_ttl', '__return_zero' );

		// First fully populate the sample for all breakpoints.
		$sample_size     = od_get_url_metrics_breakpoint_sample_size();
		$viewport_widths = array_merge( od_get_breakpoint_max_widths(), array( 1000 ) );
		foreach ( $viewport_widths as $viewport_width ) {
			$this->populate_url_metrics(
				$sample_size,
				$this->get_valid_params( array( 'viewport' => array( 'width' => $viewport_width ) ) )
			);
		}

		// The next request will be rejected because all groups are fully populated with samples.
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_body_params( $this->get_valid_params() );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Test sending viewport data that isn't needed for a specific breakpoint.
	 *
	 * @covers ::od_register_endpoint
	 * @covers ::od_handle_rest_request
	 */
	public function test_rest_request_breakpoint_not_needed_for_specific_breakpoint() {
		add_filter( 'od_url_metric_storage_lock_ttl', '__return_zero' );

		$valid_params = $this->get_valid_params( array( 'viewport' => array( 'width' => 480 ) ) );

		// First fully populate the sample for a given breakpoint.
		$sample_size = od_get_url_metrics_breakpoint_sample_size();
		$this->populate_url_metrics(
			$sample_size,
			$valid_params
		);

		// The next request will be rejected because the one group is fully populated with the needed sample size.
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_body_params( $valid_params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Test fully populating the wider viewport group and then adding one more.
	 *
	 * @covers ::od_register_endpoint
	 * @covers ::od_handle_rest_request
	 */
	public function test_rest_request_over_populate_wider_viewport_group() {
		add_filter( 'od_url_metric_storage_lock_ttl', '__return_zero' );

		// First establish a single breakpoint, so there are two groups of URL metrics
		// with viewport widths 0-480 and >481.
		$breakpoint_width = 480;
		add_filter(
			'od_breakpoint_max_widths',
			static function () use ( $breakpoint_width ): array {
				return array( $breakpoint_width );
			}
		);

		$wider_viewport_params = $this->get_valid_params( array( 'viewport' => array( 'width' => $breakpoint_width + 1 ) ) );

		// Fully populate the wider viewport group, leaving the narrower one empty.
		$sample_size = od_get_url_metrics_breakpoint_sample_size();
		$this->populate_url_metrics(
			$sample_size,
			$wider_viewport_params
		);

		// Sanity check that the groups were constructed as expected.
		$group_collection  = new OD_URL_Metrics_Group_Collection(
			OD_URL_Metrics_Post_Type::get_url_metrics_from_post( OD_URL_Metrics_Post_Type::get_post( od_get_url_metrics_slug( array() ) ) ),
			od_get_breakpoint_max_widths(),
			od_get_url_metrics_breakpoint_sample_size(),
			HOUR_IN_SECONDS
		);
		$url_metric_groups = iterator_to_array( $group_collection );
		$this->assertSame(
			array( 0, $breakpoint_width + 1 ),
			array_map(
				static function ( OD_URL_Metrics_Group $group ) {
					return $group->get_minimum_viewport_width();
				},
				$url_metric_groups
			)
		);
		$this->assertCount( 0, $url_metric_groups[0], 'Expected first group to be empty.' );
		$this->assertCount( $sample_size, end( $url_metric_groups ), 'Expected last group to be fully populated.' );

		// Now attempt to store one more URL metric for the wider viewport group.
		// This should fail because the group is already fully populated to the sample size.
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_body_params( $wider_viewport_params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status(), 'Response: ' . wp_json_encode( $response->get_data() ) );
	}

	/**
	 * Test fully populating the narrower viewport group and then adding one more.
	 *
	 * @covers ::od_register_endpoint
	 * @covers ::od_handle_rest_request
	 */
	public function test_rest_request_over_populate_narrower_viewport_group() {
		add_filter( 'od_url_metric_storage_lock_ttl', '__return_zero' );

		// First establish a single breakpoint, so there are two groups of URL metrics
		// with viewport widths 0-480 and >481.
		$breakpoint_width = 480;
		add_filter(
			'od_breakpoint_max_widths',
			static function () use ( $breakpoint_width ): array {
				return array( $breakpoint_width );
			}
		);

		$narrower_viewport_params = $this->get_valid_params( array( 'viewport' => array( 'width' => $breakpoint_width ) ) );

		// Fully populate the narrower viewport group, leaving the wider one empty.
		$this->populate_url_metrics(
			od_get_url_metrics_breakpoint_sample_size(),
			$narrower_viewport_params
		);

		// Now attempt to store one more URL metric for the narrower viewport group.
		// This should fail because the group is already fully populated to the sample size.
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_body_params( $narrower_viewport_params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status(), 'Response: ' . wp_json_encode( $response->get_data() ) );
	}

	/**
	 * Populate URL metrics.
	 *
	 * @param int   $count  Count of URL metrics to populate.
	 * @param array $params Params for URL metric.
	 */
	private function populate_url_metrics( int $count, array $params ) {
		for ( $i = 0; $i < $count; $i++ ) {
			$request = new WP_REST_Request( 'POST', self::ROUTE );
			$request->set_body_params( $params );
			$response = rest_get_server()->dispatch( $request );
			$this->assertSame( 200, $response->get_status() );
		}
	}

	/**
	 * Gets valid params.
	 *
	 * @param array $extras Extra params which are recursively merged on top of the valid params.
	 * @return array Params.
	 */
	private function get_valid_params( array $extras = array() ): array {
		$slug = od_get_url_metrics_slug( array() );
		$data = $this->get_sample_validated_url_metric();
		$data = array_merge(
			array(
				'slug'  => $slug,
				'nonce' => od_get_url_metrics_storage_nonce( $slug, $data['url'] ),
			),
			$data
		);
		unset( $data['timestamp'] ); // Since provided by default args.
		if ( $extras ) {
			$data = $this->recursive_merge( $data, $extras );
		}
		return $data;
	}

	/**
	 * Merges arrays recursively non-array values being overridden.
	 *
	 * This is on contrast with `array_merge_recursive()` which creates arrays for colliding values.
	 *
	 * @param array $base_array   Base array.
	 * @param array $sparse_array Sparse array.
	 * @return array Merged array.
	 */
	private function recursive_merge( array $base_array, array $sparse_array ): array {
		foreach ( $sparse_array as $key => $value ) {
			if (
				array_key_exists( $key, $base_array ) &&
				is_array( $base_array[ $key ] ) &&
				is_array( $value )
			) {
				$base_array[ $key ] = $this->recursive_merge( $base_array[ $key ], $value );
			} else {
				$base_array[ $key ] = $value;
			}
		}
		return $base_array;
	}

	/**
	 * Gets sample validated URL metric data.
	 *
	 * @return array
	 */
	private function get_sample_validated_url_metric(): array {
		return array(
			'url'       => home_url( '/' ),
			'viewport'  => array(
				'width'  => 480,
				'height' => 640,
			),
			'timestamp' => microtime( true ),
			'elements'  => array(
				array(
					'isLCP'             => true,
					'isLCPCandidate'    => true,
					'xpath'             => '/*[0][self::HTML]/*[1][self::BODY]/*[0][self::DIV]/*[1][self::MAIN]/*[0][self::DIV]/*[0][self::FIGURE]/*[0][self::IMG]',
					'intersectionRatio' => 1,
				),
			),
		);
	}
}
