<?php
/**
 * Tests for optimization-detective plugin storage/rest-api.php.
 *
 * @package optimization-detective
 */

class Test_OD_Storage_REST_API extends WP_UnitTestCase {
	use Optimization_Detective_Test_Helpers;

	/**
	 * @var string
	 */
	const ROUTE = '/' . OD_REST_API_NAMESPACE . OD_URL_METRICS_ROUTE;

	/**
	 * Test od_register_endpoint().
	 *
	 * @covers ::od_register_endpoint
	 */
	public function test_od_register_endpoint_hooked(): void {
		$this->assertSame( 10, has_action( 'rest_api_init', 'od_register_endpoint' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed>
	 */
	public function data_provider_to_test_rest_request_good_params(): array {
		return array(
			'not_extended' => array(
				'set_up' => function () {
					return $this->get_valid_params();
				},
			),
			'extended'     => array(
				'set_up' => function () {
					add_filter(
						'od_url_metric_schema_root_additional_properties',
						static function ( array $properties ): array {
							$properties['extra'] = array(
								'type' => 'string',
							);
							return $properties;
						}
					);

					$params = $this->get_valid_params();
					$params['extra'] = 'foo';
					return $params;
				},
			),
		);
	}

	/**
	 * Test good params.
	 *
	 * @dataProvider data_provider_to_test_rest_request_good_params
	 *
	 * @covers ::od_register_endpoint
	 * @covers ::od_handle_rest_request
	 */
	public function test_rest_request_good_params( Closure $set_up ): void {
		$valid_params = $set_up();
		$this->assertCount( 0, get_posts( array( 'post_type' => OD_URL_Metrics_Post_Type::SLUG ) ) );
		$request  = $this->create_request( $valid_params );
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

		$expected_data = $valid_params;
		unset( $expected_data['nonce'], $expected_data['slug'] );
		$this->assertSame(
			$expected_data,
			wp_array_slice_assoc( $url_metrics[0]->jsonSerialize(), array_keys( $expected_data ) )
		);
	}

	/**
	 * Data provider for test_rest_request_bad_params.
	 *
	 * @return array<string, mixed> Test data.
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
				'invalid_viewport_aspect_ratio'            => array(
					'viewport' => array(
						'width'  => 1024,
						'height' => 12000,
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
				'invalid_elements_additional_intersect_rect_property' => array(
					'elements' => array(
						array_merge(
							$valid_element,
							array(
								'intersectionRect' => array(
									'width'  => 640,
									'height' => 480,
									'wooHoo' => 'bad',
								),
							)
						),
					),
				),
				'invalid_elements_negative_width_intersect_rect_property' => array(
					'elements' => array(
						array_merge(
							$valid_element,
							array(
								'intersectionRect' => array(
									'width'  => -640,
									'height' => 480,
								),
							)
						),
					),
				),
				'invalid_root_property'                    => array(
					'is_touch' => false,
				),
				'invalid_element_property'                 => array(
					'elements' => array(
						array_merge(
							$valid_element,
							array(
								'is_big' => true,
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
	 *
	 * @param array<string, mixed> $params Params.
	 */
	public function test_rest_request_bad_params( array $params ): void {
		$request  = $this->create_request( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 400, $response->get_status(), 'Response: ' . wp_json_encode( $response ) );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'], 'Response: ' . wp_json_encode( $response ) );

		$this->assertNull( OD_URL_Metrics_Post_Type::get_post( $params['slug'] ) );
	}

	/**
	 * Test not sending JSON data.
	 *
	 * @covers ::od_register_endpoint
	 * @covers ::od_handle_rest_request
	 */
	public function test_rest_request_not_json_data(): void {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_body_params( $this->get_valid_params() ); // Valid and yet set as POST params and not as JSON body, so this is why it fails.
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 400, $response->get_status(), 'Response: ' . wp_json_encode( $response ) );
		$this->assertSame( 'missing_array_json_body', $response->get_data()['code'], 'Response: ' . wp_json_encode( $response ) );
	}

	/**
	 * Test not sending JSON Content-Type.
	 *
	 * @covers ::od_register_endpoint
	 * @covers ::od_handle_rest_request
	 */
	public function test_rest_request_not_json_content_type(): void {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_body( wp_json_encode( $this->get_valid_params() ) );
		$request->set_header( 'Content-Type', 'text/plain' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 400, $response->get_status(), 'Response: ' . wp_json_encode( $response ) );
		$this->assertSame( 'rest_missing_callback_param', $response->get_data()['code'], 'Response: ' . wp_json_encode( $response ) );
	}

	/**
	 * Test empty array JSON body.
	 *
	 * @covers ::od_register_endpoint
	 * @covers ::od_handle_rest_request
	 */
	public function test_rest_request_empty_array_json_body(): void {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_body( '[]' );
		$request->set_header( 'Content-Type', 'application/json' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 400, $response->get_status(), 'Response: ' . wp_json_encode( $response ) );
		$this->assertSame( 'rest_missing_callback_param', $response->get_data()['code'], 'Response: ' . wp_json_encode( $response ) );
	}

	/**
	 * Test non-array JSON body.
	 *
	 * @covers ::od_register_endpoint
	 * @covers ::od_handle_rest_request
	 */
	public function test_rest_request_non_array_json_body(): void {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_body( '"Hello World!"' );
		$request->set_header( 'Content-Type', 'application/json' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 400, $response->get_status(), 'Response: ' . wp_json_encode( $response ) );
		$this->assertSame( 'rest_missing_callback_param', $response->get_data()['code'], 'Response: ' . wp_json_encode( $response ) );
	}

	/**
	 * Test timestamp ignored.
	 *
	 * @covers ::od_register_endpoint
	 * @covers ::od_handle_rest_request
	 */
	public function test_rest_request_timestamp_ignored(): void {
		$initial_microtime = microtime( true );

		$params   = $this->get_valid_params(
			array(
				// Both should be ignored since they are read-only.
				'timestamp' => microtime( true ) - HOUR_IN_SECONDS,
				'uuid'      => wp_generate_uuid4(),
			)
		);
		$request  = $this->create_request( $params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 200, $response->get_status(), 'Response: ' . wp_json_encode( $response ) );

		$post = OD_URL_Metrics_Post_Type::get_post( $params['slug'] );
		$this->assertInstanceOf( WP_Post::class, $post );

		$url_metrics = OD_URL_Metrics_Post_Type::get_url_metrics_from_post( $post );
		$this->assertCount( 1, $url_metrics );
		$url_metric = $url_metrics[0];
		$this->assertNotEquals( $params['timestamp'], $url_metric->get_timestamp() );
		$this->assertTrue( wp_is_uuid( $url_metric->get_uuid() ), $url_metric->get_uuid() );
		$this->assertNotEquals( $params['uuid'], $url_metric->get_uuid() );
		$this->assertGreaterThanOrEqual( $initial_microtime, $url_metric->get_timestamp() );
	}

	/**
	 * Test REST API request when metric storage is locked.
	 *
	 * @covers ::od_register_endpoint
	 * @covers ::od_handle_rest_request
	 */
	public function test_rest_request_locked(): void {
		OD_Storage_Lock::set_lock();

		$request = $this->create_request( $this->get_valid_params() );

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
	public function test_rest_request_breakpoint_not_needed_for_any_breakpoint(): void {
		add_filter( 'od_url_metric_storage_lock_ttl', '__return_zero' );

		// First fully populate the sample for all breakpoints.
		$sample_size     = od_get_url_metrics_breakpoint_sample_size();
		$viewport_widths = array_merge( od_get_breakpoint_max_widths(), array( 1000 ) );
		foreach ( $viewport_widths as $viewport_width ) {
			$this->populate_url_metrics(
				$sample_size,
				$this->get_valid_params(
					array(
						'viewport' => array(
							'width'  => $viewport_width,
							'height' => ceil( $viewport_width / 2 ),
						),
					)
				)
			);
		}

		// The next request will be rejected because all groups are fully populated with samples.
		$request  = $this->create_request( $this->get_valid_params() );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Test sending viewport data that isn't needed for a specific breakpoint.
	 *
	 * @covers ::od_register_endpoint
	 * @covers ::od_handle_rest_request
	 */
	public function test_rest_request_breakpoint_not_needed_for_specific_breakpoint(): void {
		add_filter( 'od_url_metric_storage_lock_ttl', '__return_zero' );

		$valid_params = $this->get_valid_params( array( 'viewport' => array( 'width' => 480 ) ) );

		// First fully populate the sample for a given breakpoint.
		$sample_size = od_get_url_metrics_breakpoint_sample_size();
		$this->populate_url_metrics(
			$sample_size,
			$valid_params
		);

		// The next request will be rejected because the one group is fully populated with the needed sample size.
		$request  = $this->create_request( $this->get_valid_params() );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Test fully populating the wider viewport group and then adding one more.
	 *
	 * @covers ::od_register_endpoint
	 * @covers ::od_handle_rest_request
	 */
	public function test_rest_request_over_populate_wider_viewport_group(): void {
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
		$request  = $this->create_request( $wider_viewport_params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status(), 'Response: ' . wp_json_encode( $response->get_data() ) );
	}

	/**
	 * Test fully populating the narrower viewport group and then adding one more.
	 *
	 * @covers ::od_register_endpoint
	 * @covers ::od_handle_rest_request
	 */
	public function test_rest_request_over_populate_narrower_viewport_group(): void {
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
		$request  = $this->create_request( $narrower_viewport_params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status(), 'Response: ' . wp_json_encode( $response->get_data() ) );
	}

	/**
	 * Populate URL metrics.
	 *
	 * @param int                  $count  Count of URL metrics to populate.
	 * @param array<string, mixed> $params Params for URL metric.
	 */
	private function populate_url_metrics( int $count, array $params ): void {
		for ( $i = 0; $i < $count; $i++ ) {
			$request  = $this->create_request( $params );
			$response = rest_get_server()->dispatch( $request );
			$this->assertSame( 200, $response->get_status() );
		}
	}

	/**
	 * Gets valid params.
	 *
	 * @param array<string, mixed> $extras Extra params which are recursively merged on top of the valid params.
	 * @return array<string, mixed> Params.
	 */
	private function get_valid_params( array $extras = array() ): array {
		$slug = od_get_url_metrics_slug( array() );
		$data = $this->get_sample_url_metric(
			array(
				'viewport_width' => 480,
				'element'        => array(
					'xpath' => '/*[0][self::HTML]/*[1][self::BODY]/*[0][self::DIV]/*[1][self::MAIN]/*[0][self::DIV]/*[0][self::FIGURE]/*[0][self::IMG]',
				),
			)
		)->jsonSerialize();
		unset( $data['timestamp'], $data['uuid'] ); // Since these are readonly.
		$data = array_merge(
			array(
				'slug'  => $slug,
				'nonce' => od_get_url_metrics_storage_nonce( $slug, $data['url'] ),
			),
			$data
		);
		if ( count( $extras ) > 0 ) {
			$data = $this->recursive_merge( $data, $extras );
		}
		return $data;
	}

	/**
	 * Merges arrays recursively non-array values being overridden.
	 *
	 * This is on contrast with `array_merge_recursive()` which creates arrays for colliding values.
	 *
	 * @param array<string, mixed> $base_array   Base array.
	 * @param array<string, mixed> $sparse_array Sparse array.
	 * @return array<string, mixed> Merged array.
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
	 * Creates a request to store a URL metric.
	 *
	 * @param array<string, mixed> $params Params.
	 * @return WP_REST_Request<array<string, mixed>> Request.
	 */
	private function create_request( array $params ): WP_REST_Request {
		/**
		 * Request.
		 *
		 * @var WP_REST_Request<array<string, mixed>> $request
		 */
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_query_params( wp_array_slice_assoc( $params, array( 'nonce', 'slug' ) ) );
		unset( $params['nonce'], $params['slug'] );
		$request->set_body( wp_json_encode( $params ) );
		return $request;
	}
}
