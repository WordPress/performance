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
		$add_root_extra_property = static function ( string $property_name ): void {
			add_filter(
				'od_url_metric_schema_root_additional_properties',
				static function ( array $properties ) use ( $property_name ): array {
					$properties[ $property_name ] = array(
						'type' => 'string',
					);
					return $properties;
				}
			);
		};

		return array(
			'not_extended'             => array(
				'set_up' => function (): array {
					return $this->get_valid_params();
				},
			),
			'extended'                 => array(
				'set_up' => function () use ( $add_root_extra_property ): array {
					$add_root_extra_property( 'extra' );
					$params = $this->get_valid_params();
					$params['extra'] = 'foo';
					return $params;
				},
			),
			'with_cache_purge_post_id' => array(
				'set_up' => function (): array {
					$params = $this->get_valid_params();
					$params['cache_purge_post_id'] = self::factory()->post->create();
					$params['url'] = get_permalink( $params['cache_purge_post_id'] );
					$params['slug'] = od_get_url_metrics_slug( array( 'p' => $params['cache_purge_post_id'] ) );
					$params['hmac'] = od_get_url_metrics_storage_hmac( $params['slug'], $params['current_etag'], $params['url'], $params['cache_purge_post_id'] );
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
	 * @covers ::od_trigger_page_cache_invalidation
	 */
	public function test_rest_request_good_params( Closure $set_up ): void {
		$stored_context = null;
		add_action(
			'od_url_metric_stored',
			function ( OD_URL_Metric_Store_Request_Context $context ) use ( &$stored_context ): void {
				$this->assertInstanceOf( OD_URL_Metric_Group_Collection::class, $context->url_metric_group_collection );
				$this->assertInstanceOf( OD_URL_Metric_Group::class, $context->url_metric_group );
				$this->assertInstanceOf( OD_URL_Metric::class, $context->url_metric );
				$this->assertInstanceOf( WP_REST_Request::class, $context->request );
				$this->assertIsInt( $context->post_id );
				$stored_context = $context;
			}
		);

		$valid_params = $set_up();

		if ( isset( $valid_params['cache_purge_post_id'] ) ) {
			$this->assertFalse( wp_next_scheduled( 'od_trigger_page_cache_invalidation', array( $valid_params['cache_purge_post_id'] ) ) );
		}

		$this->assertCount( 0, get_posts( array( 'post_type' => OD_URL_Metrics_Post_Type::SLUG ) ) );
		$request  = $this->create_request( $valid_params );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 1, did_action( 'od_url_metric_stored' ) );

		$this->assertSame( 200, $response->get_status(), 'Response: ' . wp_json_encode( $response ) );
		$data = $response->get_data();
		$this->assertCount( 1, get_posts( array( 'post_type' => OD_URL_Metrics_Post_Type::SLUG ) ) );

		$this->assertTrue( $data['success'] );

		$post = OD_URL_Metrics_Post_Type::get_post( $valid_params['slug'] );
		$this->assertInstanceOf( WP_Post::class, $post );

		$url_metrics = OD_URL_Metrics_Post_Type::get_url_metrics_from_post( $post );
		$this->assertCount( 1, $url_metrics, 'Expected number of URL Metrics stored.' );
		$this->assertSame( $valid_params['elements'], $this->get_array_json_data( $url_metrics[0]->get( 'elements' ) ) );
		$this->assertSame( $valid_params['viewport']['width'], $url_metrics[0]->get_viewport_width() );

		$expected_data = $valid_params;
		unset( $expected_data['hmac'], $expected_data['slug'], $expected_data['current_etag'], $expected_data['cache_purge_post_id'] );
		unset( $expected_data['unset_prop'] );
		$this->assertSame(
			$expected_data,
			wp_array_slice_assoc( $url_metrics[0]->jsonSerialize(), array_keys( $expected_data ) )
		);

		$this->assertInstanceOf( OD_URL_Metric_Store_Request_Context::class, $stored_context );

		// Now check that od_trigger_page_cache_invalidation() cleaned caches as expected.
		$this->assertSame( $url_metrics[0]->jsonSerialize(), $stored_context->url_metric->jsonSerialize() );
		if ( isset( $valid_params['cache_purge_post_id'] ) ) {
			$cache_purge_post_id = $stored_context->request->get_param( 'cache_purge_post_id' );
			$this->assertSame( $valid_params['cache_purge_post_id'], $cache_purge_post_id );
			$scheduled = wp_next_scheduled( 'od_trigger_page_cache_invalidation', array( $cache_purge_post_id ) );
			$this->assertIsInt( $scheduled );
			$this->assertGreaterThan( time(), $scheduled );
		}
	}

	/**
	 * Data provider for test_rest_request_bad_params.
	 *
	 * @return array<string, mixed> Test data.
	 */
	public function data_provider_invalid_params(): array {
		$valid_params  = $this->get_valid_params();
		$valid_element = $valid_params['elements'][0];

		return array_map(
			static function ( $params ) use ( $valid_params ) {
				return array(
					'params' => array_merge( $valid_params, $params ),
				);
			},
			array(
				'bad_url'                                  => array(
					'url' => 'bad://url',
				),
				'bad_current_etag1'                        => array(
					'current_etag' => 'foo',
				),
				'bad_current_etag2'                        => array(
					'current_etag' => $valid_params['current_etag'] . "\n",
				),
				'bad_slug'                                 => array(
					'slug' => '<script>document.write("evil")</script>',
				),
				'bad_hmac'                                 => array(
					'hmac' => 'not even a hash',
				),
				'invalid_hmac'                             => array(
					'hmac' => od_get_url_metrics_storage_hmac( od_get_url_metrics_slug( array( 'different' => 'query vars' ) ), $valid_params['current_etag'], home_url( '/' ) ),
				),
				'invalid_hmac_with_queried_object'         => array(
					'hmac' => od_get_url_metrics_storage_hmac( od_get_url_metrics_slug( array() ), $valid_params['current_etag'], home_url( '/' ), 1 ),
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
		$this->assertSame( 0, did_action( 'od_url_metric_stored' ) );
	}

	/**
	 * Test sending data when no Origin request header is sent.
	 *
	 * @covers ::od_register_endpoint
	 * @covers ::od_handle_rest_request
	 * @covers ::od_is_allowed_http_origin
	 */
	public function test_rest_request_without_origin(): void {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_body_params( $this->get_valid_params() ); // Valid and yet set as POST params and not as JSON body, so this is why it fails.
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status(), 'Response: ' . wp_json_encode( $response ) );
		$this->assertSame( 'rest_cross_origin_forbidden', $response->get_data()['code'], 'Response: ' . wp_json_encode( $response ) );
		$this->assertSame( 0, did_action( 'od_url_metric_stored' ) );
	}

	/**
	 * Test sending data when a cross-domain Origin request header is sent.
	 *
	 * @covers ::od_register_endpoint
	 * @covers ::od_handle_rest_request
	 * @covers ::od_is_allowed_http_origin
	 */
	public function test_rest_request_cross_origin(): void {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_header( 'Origin', 'https://cross-origin.example.com' );
		$request->set_body_params( $this->get_valid_params() ); // Valid and yet set as POST params and not as JSON body, so this is why it fails.
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status(), 'Response: ' . wp_json_encode( $response ) );
		$this->assertSame( 'rest_cross_origin_forbidden', $response->get_data()['code'], 'Response: ' . wp_json_encode( $response ) );
		$this->assertSame( 0, did_action( 'od_url_metric_stored' ) );
	}

	/**
	 * Test REST API request when 'home_url' is filtered.
	 *
	 * @covers ::od_register_endpoint
	 * @covers ::od_handle_rest_request
	 * @covers ::od_is_allowed_http_origin
	 */
	public function test_rest_request_origin_when_home_url_filtered(): void {
		$request = $this->create_request( $this->get_valid_params() );
		add_filter(
			'home_url',
			static function ( string $url ): string {
				return trailingslashit( $url ) . 'home/en/?foo=bar#baz';
			}
		);
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Test not sending JSON data.
	 *
	 * @covers ::od_register_endpoint
	 * @covers ::od_handle_rest_request
	 */
	public function test_rest_request_not_json_data(): void {
		$request = new WP_REST_Request( 'POST', self::ROUTE );
		$request->set_header( 'Origin', home_url() );
		$request->set_body_params( $this->get_valid_params() ); // Valid and yet set as POST params and not as JSON body, so this is why it fails.
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 400, $response->get_status(), 'Response: ' . wp_json_encode( $response ) );
		$this->assertSame( 'missing_array_json_body', $response->get_data()['code'], 'Response: ' . wp_json_encode( $response ) );
		$this->assertSame( 0, did_action( 'od_url_metric_stored' ) );
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
		$this->assertSame( 0, did_action( 'od_url_metric_stored' ) );
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
		$this->assertSame( 0, did_action( 'od_url_metric_stored' ) );
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
		$this->assertSame( 0, did_action( 'od_url_metric_stored' ) );
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

		// First establish a single breakpoint, so there are two groups of URL Metrics
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
		$group_collection  = new OD_URL_Metric_Group_Collection(
			OD_URL_Metrics_Post_Type::get_url_metrics_from_post( OD_URL_Metrics_Post_Type::get_post( od_get_url_metrics_slug( array() ) ) ),
			$wider_viewport_params['current_etag'],
			od_get_breakpoint_max_widths(),
			od_get_url_metrics_breakpoint_sample_size(),
			HOUR_IN_SECONDS
		);
		$url_metric_groups = iterator_to_array( $group_collection );
		$this->assertSame(
			array( 0, $breakpoint_width + 1 ),
			array_map(
				static function ( OD_URL_Metric_Group $group ) {
					return $group->get_minimum_viewport_width();
				},
				$url_metric_groups
			)
		);
		$this->assertCount( 0, $url_metric_groups[0], 'Expected first group to be empty.' );
		$this->assertCount( $sample_size, end( $url_metric_groups ), 'Expected last group to be fully populated.' );

		// Now attempt to store one more URL Metric for the wider viewport group.
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

		// First establish a single breakpoint, so there are two groups of URL Metrics
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

		// Now attempt to store one more URL Metric for the narrower viewport group.
		// This should fail because the group is already fully populated to the sample size.
		$request  = $this->create_request( $narrower_viewport_params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status(), 'Response: ' . wp_json_encode( $response->get_data() ) );
	}

	/**
	 * Test od_trigger_page_cache_invalidation().
	 *
	 * @covers ::od_trigger_page_cache_invalidation
	 */
	public function test_od_trigger_page_cache_invalidation(): void {
		$cache_purge_post_id = self::factory()->post->create();

		$all_hook_callback_args = array();
		add_action(
			'all',
			static function ( string $hook, ...$args ) use ( &$all_hook_callback_args ): void {
				$all_hook_callback_args[ $hook ][] = $args;
			},
			10,
			PHP_INT_MAX
		);

		od_trigger_page_cache_invalidation( $cache_purge_post_id );

		$this->assertArrayHasKey( 'clean_post_cache', $all_hook_callback_args );
		$found = false;
		foreach ( $all_hook_callback_args['clean_post_cache'] as $args ) {
			if ( $args[0] === $cache_purge_post_id ) {
				$this->assertInstanceOf( WP_Post::class, $args[1] );
				$this->assertSame( $cache_purge_post_id, $args[1]->ID );
				$found = true;
			}
		}
		$this->assertTrue( $found, 'Expected clean_post_cache to have been fired for the post queried object.' );

		$this->assertArrayHasKey( 'transition_post_status', $all_hook_callback_args );
		$found = false;
		foreach ( $all_hook_callback_args['transition_post_status'] as $args ) {
			$this->assertInstanceOf( WP_Post::class, $args[2] );
			if ( $args[2]->ID === $cache_purge_post_id ) {
				$this->assertSame( $args[2]->post_status, $args[0] );
				$this->assertSame( $args[2]->post_status, $args[1] );
				$found = true;
			}
		}
		$this->assertTrue( $found, 'Expected transition_post_status to have been fired for the post queried object.' );

		$this->assertArrayHasKey( 'save_post', $all_hook_callback_args );
		$found = false;
		foreach ( $all_hook_callback_args['save_post'] as $args ) {
			if ( $args[0] === $cache_purge_post_id ) {
				$this->assertInstanceOf( WP_Post::class, $args[1] );
				$this->assertSame( $cache_purge_post_id, $args[1]->ID );
				$found = true;
			}
		}
		$this->assertTrue( $found, 'Expected save_post to have been fired for the post queried object.' );
	}

	/**
	 * Populate URL Metrics.
	 *
	 * @param int                  $count  Count of URL Metrics to populate.
	 * @param array<string, mixed> $params Params for URL Metric.
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
					'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::DIV]/*[2][self::MAIN]/*[1][self::DIV]/*[1][self::FIGURE]/*[1][self::IMG]',
				),
			)
		)->jsonSerialize();
		$data = array_merge(
			array(
				'slug'         => $slug,
				'hmac'         => od_get_url_metrics_storage_hmac( $slug, $data['etag'], $data['url'] ),
				'current_etag' => $data['etag'],
			),
			$data
		);
		unset( $data['timestamp'], $data['uuid'], $data['etag'] ); // Since these are readonly.
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
	 * Creates a request to store a URL Metric.
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
		$request->set_query_params( wp_array_slice_assoc( $params, array( 'hmac', 'current_etag', 'slug', 'cache_purge_post_id' ) ) );
		$request->set_header( 'Origin', home_url() );
		unset( $params['hmac'], $params['slug'], $params['current_etag'], $params['cache_purge_post_id'] );
		$request->set_body( wp_json_encode( $params ) );
		return $request;
	}
}
