<?php
/**
 * Tests for optimization-detective plugin storage/class-od-rest-url-metrics-store-endpoint.php aka OD_REST_URL_Metrics_Store_Endpoint.
 *
 * @package optimization-detective
 */

/**
 * Class Test_OD_REST_URL_Metrics_Store_Endpoint used to test `OD_REST_URL_Metrics_Store_Endpoint` class.
 *
 * @since n.e.x.t
 *
 * @noinspection PhpUnhandledExceptionInspection
 * @noinspection PhpDocMissingThrowsInspection
 */
class Test_OD_REST_URL_Metrics_Store_Endpoint extends WP_UnitTestCase {
	use Optimization_Detective_Test_Helpers;

	/**
	 * Sets up.
	 */
	public function set_up(): void {
		parent::set_up();
		unset( $GLOBALS['wp_rest_server'] );
	}

	/**
	 * Tears down.
	 */
	public function tear_down(): void {
		parent::tear_down();
		unset( $GLOBALS['wp_rest_server'] );
	}

	/**
	 * Gets the route.
	 *
	 * @return string Route.
	 */
	private function get_route(): string {
		return '/' . OD_REST_URL_Metrics_Store_Endpoint::ROUTE_NAMESPACE . OD_REST_URL_Metrics_Store_Endpoint::ROUTE_BASE;
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
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::get_registration_args
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::decompress_rest_request_body
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::store_permissions_check
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::handle_rest_request
	 * @covers OD_Strict_URL_Metric::set_additional_properties_to_false
	 * @covers OD_URL_Metric_Store_Request_Context::__construct
	 * @covers OD_URL_Metric_Store_Request_Context::__get
	 */
	public function test_rest_request_good_params_and_success( Closure $set_up ): void {
		$stored_context = null;
		add_action(
			'od_url_metric_stored',
			function ( OD_URL_Metric_Store_Request_Context $context ) use ( &$stored_context ): void {
				$this->assertInstanceOf( OD_URL_Metric_Group_Collection::class, $context->url_metric_group_collection );
				$this->assertInstanceOf( OD_URL_Metric_Group::class, $context->url_metric_group );
				$this->assertInstanceOf( OD_URL_Metric::class, $context->url_metric );
				$this->assertInstanceOf( WP_REST_Request::class, $context->request );
				$this->assertIsInt( $context->url_metrics_id );
				$this->setExpectedIncorrectUsage( 'OD_URL_Metric_Store_Request_Context::$post_id' );
				$this->assertIsInt( $context->post_id );
				$this->assertSame( $context->url_metrics_id, $context->post_id );

				$error = null;
				$value = '';
				try {
					$value = $context->__get( 'unknown' );
				} catch ( Error $e ) {
					$error = $e;
				}
				$this->assertSame( '', $value );
				$this->assertInstanceOf( Error::class, $error );
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
		$this->assertFalse( $response->is_error(), $response->is_error() ? $response->as_error()->get_error_message() : '' );

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
		$element = $url_metrics[0]->get( 'elements' )[0];
		$this->assertStringStartsWith( '/HTML/BODY/DIV[@id=\'page\']/', $element->jsonSerialize()['xpath'] );

		$expected_data = $valid_params;
		unset( $expected_data['hmac'], $expected_data['slug'], $expected_data['current_etag'], $expected_data['cache_purge_post_id'] );
		unset( $expected_data['unset_prop'] );
		$this->assertSame(
			$expected_data,
			wp_array_slice_assoc( $url_metrics[0]->jsonSerialize(), array_keys( $expected_data ) )
		);

		$this->assertInstanceOf( OD_URL_Metric_Store_Request_Context::class, $stored_context );

		// Now check that od_trigger_post_update_actions() cleaned caches as expected.
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
	 * Test good params.
	 *
	 * @dataProvider data_provider_to_test_rest_request_good_params
	 *
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::get_registration_args
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::decompress_rest_request_body
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::store_permissions_check
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::handle_rest_request
	 * @covers OD_Strict_URL_Metric::set_additional_properties_to_false
	 */
	public function test_rest_request_good_params_but_post_save_failed( Closure $set_up ): void {
		$valid_params = $set_up();

		add_filter( 'wp_insert_post_empty_content', '__return_true' ); // Cause wp_insert_post() to return WP_Error.

		$request  = $this->create_request( $valid_params );
		$response = rest_get_server()->dispatch( $request );

		$error = $response->as_error();
		$this->assertInstanceOf( WP_Error::class, $error );
		$this->assertSame( 'unable_to_store_url_metric', $error->get_error_code() );
	}

	/**
	 * Data provider for test_rest_request_bad_params.
	 *
	 * @return array<string, mixed> Test data.
	 */
	public function data_provider_invalid_params(): array {
		$valid_params  = $this->get_valid_params();
		$valid_element = $valid_params['elements'][0];

		return array(
			'missing_callback_params'                  => array(
				'params'          => array(
					'slug' => $valid_params['slug'],
				),
				'expected_status' => 400,
				'expected_code'   => 'rest_missing_callback_param',
			),
			'bad_url'                                  => array(
				'params'          => array_merge(
					$valid_params,
					array(
						'url' => 'bad://url',
					)
				),
				'expected_status' => 400,
				'expected_code'   => 'rest_invalid_param',
			),
			'bad_current_etag1'                        => array(
				'params'          => array_merge(
					$valid_params,
					array(
						'current_etag' => 'foo',
					)
				),
				'expected_status' => 400,
				'expected_code'   => 'rest_invalid_param',
			),
			'bad_current_etag2'                        => array(
				'params'          => array_merge(
					$valid_params,
					array(
						'current_etag' => $valid_params['current_etag'] . "\n",
					)
				),
				'expected_status' => 400,
				'expected_code'   => 'rest_invalid_param',
			),
			'bad_slug'                                 => array(
				'params'          => array_merge(
					$valid_params,
					array(
						'slug' => '<script>document.write("evil")</script>',
					)
				),
				'expected_status' => 400,
				'expected_code'   => 'rest_invalid_param',
			),
			'bad_hmac'                                 => array(
				'params'          => array_merge(
					$valid_params,
					array(
						'hmac' => 'not even a hash',
					)
				),
				'expected_status' => 400,
				'expected_code'   => 'rest_invalid_param',
			),
			'invalid_hmac'                             => array(
				'params'          => array_merge(
					$valid_params,
					array(
						'hmac' => od_get_url_metrics_storage_hmac( od_get_url_metrics_slug( array( 'different' => 'query vars' ) ), $valid_params['current_etag'], home_url( '/' ) ),
					)
				),
				'expected_status' => 400,
				'expected_code'   => 'rest_invalid_param',
			),
			'invalid_hmac_with_queried_object'         => array(
				'params'          => array_merge(
					$valid_params,
					array(
						'hmac' => od_get_url_metrics_storage_hmac( od_get_url_metrics_slug( array() ), $valid_params['current_etag'], home_url( '/' ), 1 ),
					)
				),
				'expected_status' => 400,
				'expected_code'   => 'rest_invalid_param',
			),
			'invalid_viewport_type'                    => array(
				'params'          => array_merge(
					$valid_params,
					array(
						'viewport' => '640x480',
					)
				),
				'expected_status' => 400,
				'expected_code'   => 'rest_invalid_param',
			),
			'invalid_viewport_values'                  => array(
				'params'          => array_merge(
					$valid_params,
					array(
						'viewport' => array(
							'breadth' => 100,
							'depth'   => 200,
						),
					)
				),
				'expected_status' => 400,
				'expected_code'   => 'rest_invalid_param',
			),
			'invalid_viewport_aspect_ratio'            => array(
				'params'          => array_merge(
					$valid_params,
					array(
						'viewport' => array(
							'width'  => 1024,
							'height' => 12000,
						),
					)
				),
				'expected_status' => 400,
				'expected_code'   => 'rest_invalid_param',
			),
			'invalid_elements_type'                    => array(
				'params'          => array_merge(
					$valid_params,
					array(
						'elements' => 'bad',
					)
				),
				'expected_status' => 400,
				'expected_code'   => 'rest_invalid_param',
			),
			'invalid_elements_prop_is_lcp'             => array(
				'params'          => array_merge(
					$valid_params,
					array(
						'elements' => array(
							array_merge(
								$valid_element,
								array(
									'isLCP' => 'totally!',
								)
							),
						),
					)
				),
				'expected_status' => 400,
				'expected_code'   => 'rest_invalid_param',
			),
			'invalid_elements_prop_xpath'              => array(
				'params'          => array_merge(
					$valid_params,
					array(
						'elements' => array(
							array_merge(
								$valid_element,
								array(
									'xpath' => 'html > body img',
								)
							),
						),
					)
				),
				'expected_status' => 400,
				'expected_code'   => 'rest_invalid_param',
			),
			'invalid_content_length'                   => array(
				'params'          => array_merge(
					$valid_params,
					array(
						// Fill the JSON with more than 64KB of incomprehensible data.
						'elements' => array(
							array_merge(
								$valid_element,
								array(
									'xpath' => sprintf( '/HTML/BODY/DIV[@id=\'%s\']/*[1][self::DIV]', bin2hex( random_bytes( KB_IN_BYTES * 65 ) ) ),
								)
							),
						),
					)
				),
				'expected_status' => 413,
				'expected_code'   => 'rest_content_too_large',
			),
			'invalid_decoded_json_body_content_length' => array(
				'params'          => array_merge(
					$valid_params,
					array(
						// Fill the JSON with more than 1MB of highly compressible data.
						'elements' => array(
							array_merge(
								$valid_element,
								array(
									'xpath' => sprintf( '/HTML/BODY/DIV[@id=\'%s\']/*[1][self::DIV]', str_repeat( 'A', MB_IN_BYTES ) ),
								)
							),
						),
					)
				),
				'expected_status' => 413,
				'expected_code'   => 'rest_content_too_large',
			),
			'invalid_elements_prop_intersection_ratio' => array(
				'params'          => array_merge(
					$valid_params,
					array(
						'elements' => array(
							array_merge(
								$valid_element,
								array(
									'intersectionRatio' => - 1,
								)
							),
						),
					)
				),
				'expected_status' => 400,
				'expected_code'   => 'rest_invalid_param',
			),
			'invalid_elements_additional_intersect_rect_property' => array(
				'params'          => array_merge(
					$valid_params,
					array(
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
					)
				),
				'expected_status' => 400,
				'expected_code'   => 'rest_invalid_param',
			),
			'invalid_elements_negative_width_intersect_rect_property' => array(
				'params'          => array_merge(
					$valid_params,
					array(
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
					)
				),
				'expected_status' => 400,
				'expected_code'   => 'rest_invalid_param',
			),
			'invalid_root_property'                    => array(
				'params'          => array_merge(
					$valid_params,
					array(
						'is_touch' => false,
					)
				),
				'expected_status' => 400,
				'expected_code'   => 'rest_invalid_param',
			),
			'invalid_element_property'                 => array(
				'params'          => array_merge(
					$valid_params,
					array(
						'elements' => array(
							array_merge(
								$valid_element,
								array(
									'is_big' => true,
								)
							),
						),
					)
				),
				'expected_status' => 400,
				'expected_code'   => 'rest_invalid_param',
			),
		);
	}

	/**
	 * Test bad params.
	 *
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::get_registration_args
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::decompress_rest_request_body
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::store_permissions_check
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::handle_rest_request
	 * @covers OD_Strict_URL_Metric::set_additional_properties_to_false
	 *
	 * @dataProvider data_provider_invalid_params
	 *
	 * @param array<string, mixed> $params Params.
	 */
	public function test_rest_request_bad_params( array $params, int $expected_status, string $expected_code ): void {
		$request  = $this->create_request( $params );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( $expected_status, $response->get_status(), 'Response: ' . wp_json_encode( $response ) );
		$this->assertSame( $expected_code, $response->get_data()['code'], 'Response: ' . wp_json_encode( $response ) );

		$this->assertNull( OD_URL_Metrics_Post_Type::get_post( $params['slug'] ) );
		$this->assertSame( 0, did_action( 'od_url_metric_stored' ) );
	}

	/**
	 * Test sending data when no Origin request header is sent.
	 *
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::get_registration_args
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::decompress_rest_request_body
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::store_permissions_check
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::handle_rest_request
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::is_allowed_http_origin
	 */
	public function test_rest_request_without_origin(): void {
		$request = new WP_REST_Request( 'POST', $this->get_route() );
		$request->set_body_params( $this->get_valid_params() ); // Valid and yet set as POST params and not as JSON body, so this is why it fails.
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 403, $response->get_status(), 'Response: ' . wp_json_encode( $response ) );
		$this->assertSame( 'rest_cross_origin_forbidden', $response->get_data()['code'], 'Response: ' . wp_json_encode( $response ) );
		$this->assertSame( 0, did_action( 'od_url_metric_stored' ) );
	}

	/**
	 * Test sending data when a cross-domain Origin request header is sent.
	 *
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::get_registration_args
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::decompress_rest_request_body
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::store_permissions_check
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::handle_rest_request
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::is_allowed_http_origin
	 */
	public function test_rest_request_cross_origin(): void {
		$request = new WP_REST_Request( 'POST', $this->get_route() );
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
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::get_registration_args
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::decompress_rest_request_body
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::store_permissions_check
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::handle_rest_request
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::is_allowed_http_origin
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
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::get_registration_args
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::decompress_rest_request_body
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::store_permissions_check
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::handle_rest_request
	 */
	public function test_rest_request_not_json_data(): void {
		$request = new WP_REST_Request( 'POST', $this->get_route() );
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
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::get_registration_args
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::decompress_rest_request_body
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::store_permissions_check
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::handle_rest_request
	 */
	public function test_rest_request_not_json_content_type(): void {
		$request = new WP_REST_Request( 'POST', $this->get_route() );
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
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::get_registration_args
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::decompress_rest_request_body
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::store_permissions_check
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::handle_rest_request
	 */
	public function test_rest_request_empty_array_json_body(): void {
		$request = new WP_REST_Request( 'POST', $this->get_route() );
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
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::get_registration_args
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::decompress_rest_request_body
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::store_permissions_check
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::handle_rest_request
	 */
	public function test_rest_request_non_array_json_body(): void {
		$request = new WP_REST_Request( 'POST', $this->get_route() );
		$request->set_body( '"Hello World!"' );
		$request->set_header( 'Content-Type', 'application/json' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 400, $response->get_status(), 'Response: ' . wp_json_encode( $response ) );
		$this->assertSame( 'rest_missing_callback_param', $response->get_data()['code'], 'Response: ' . wp_json_encode( $response ) );
		$this->assertSame( 0, did_action( 'od_url_metric_stored' ) );
	}


	/**
	 * Test invalid compressed JSON body.
	 *
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::get_registration_args
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::decompress_rest_request_body
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::store_permissions_check
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::handle_rest_request
	 */
	public function test_rest_request_invalid_compressed_json_body(): void {
		$request = $this->create_request( $this->get_valid_params() );
		$request->set_body( 'Invalid compressed JSON body' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 400, $response->get_status(), 'Response: ' . wp_json_encode( $response ) );
		$this->assertSame( 'rest_invalid_payload', $response->get_data()['code'], 'Response: ' . wp_json_encode( $response ) );
		$this->assertSame( 0, did_action( 'od_url_metric_stored' ) );
	}

	/**
	 * Test timestamp ignored.
	 *
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::get_registration_args
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::decompress_rest_request_body
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::store_permissions_check
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::handle_rest_request
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
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::get_registration_args
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::decompress_rest_request_body
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::store_permissions_check
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::handle_rest_request
	 */
	public function test_rest_request_locked(): void {
		OD_Storage_Lock::set_lock();

		$request = $this->create_request( $this->get_valid_params() );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 423, $response->get_status() );
		$this->assertSame( 'url_metric_storage_locked', $response->get_data()['code'] );
	}

	/**
	 * Test sending viewport data that isn't needed for any breakpoint.
	 *
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::get_registration_args
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::decompress_rest_request_body
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::store_permissions_check
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::handle_rest_request
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
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::get_registration_args
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::decompress_rest_request_body
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::store_permissions_check
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::handle_rest_request
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
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::get_registration_args
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::decompress_rest_request_body
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::store_permissions_check
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::handle_rest_request
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
			array( 0, $breakpoint_width ),
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
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::get_registration_args
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::decompress_rest_request_body
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::store_permissions_check
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::handle_rest_request
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
	 * Test that the request is modified by ::decompress_rest_request_body().
	 *
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::decompress_rest_request_body
	 */
	public function test_od_decompress_rest_request_body_modifies_request(): void {
		$endpoint = new OD_REST_URL_Metrics_Store_Endpoint();
		$params   = $this->get_valid_params();
		$request  = $this->create_request( $this->get_valid_params() );
		unset( $params['hmac'], $params['slug'], $params['current_etag'], $params['cache_purge_post_id'] );
		$json_data = wp_json_encode( $params );
		$result    = $endpoint->decompress_rest_request_body( null, rest_get_server(), $request );

		$this->assertNotWPError( $result );
		$this->assertEquals( $json_data, $request->get_body() );
		$this->assertEquals( 'application/json', $request->get_header( 'Content-Type' ) );
	}

	/**
	 * Test that the `od_maximum_url_metric_size` filter can be used to modify the maximum size of URL Metrics.
	 *
	 * @dataProvider data_provider_maximum_url_metrics_size_filter
	 *
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::get_registration_args
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::decompress_rest_request_body
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::store_permissions_check
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::handle_rest_request
	 * @covers ::od_get_maximum_url_metric_size
	 *
	 * @param Closure              $set_up                   Set up function.
	 * @param array<string, mixed> $params                   Params.
	 * @param int                  $expected_status          Expected status.
	 * @param string|null          $expected_code            Expected code.
	 * @param bool                 $expected_incorrect_usage Expected incorrect usage.
	 */
	public function test_maximum_url_metrics_size_filter( Closure $set_up, array $params, int $expected_status, ?string $expected_code, bool $expected_incorrect_usage ): void {
		$set_up();
		if ( $expected_incorrect_usage ) {
			$this->setExpectedIncorrectUsage( 'Filter: &#039;od_maximum_url_metric_size&#039;' );
		}
		$request = $this->create_request( $params );
		unset( $params['hmac'], $params['slug'], $params['current_etag'], $params['cache_purge_post_id'] );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $params ) );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( $expected_status, $response->get_status(), 'Response: ' . wp_json_encode( $response->get_data() ) );
		if ( null !== $expected_code ) {
			$this->assertSame( $expected_code, $response->get_data()['code'] );
		}
	}

	/**
	 * Data provider for test_maximum_url_metrics_size_filter.
	 *
	 * @return array<string, mixed> Test data.
	 */
	public function data_provider_maximum_url_metrics_size_filter(): array {
		$valid_params  = $this->get_valid_params();
		$valid_element = $valid_params['elements'][0];

		return array(
			'url_metrics_should_be_accepted_because_of_increased_maximum_url_metrics_size' => array(
				'set_up'                   => static function (): void {
					add_filter(
						'od_maximum_url_metric_size',
						static function (): int {
							return MB_IN_BYTES * 2;
						}
					);
				},
				'params'                   => array_merge(
					$valid_params,
					array(
						// Fill the JSON with more than 1MB of data.
						'elements' => array(
							array_merge(
								$valid_element,
								array(
									'xpath' => sprintf( '/HTML/BODY/DIV[@id=\'%s\']/*[1][self::DIV]', str_repeat( 'A', MB_IN_BYTES ) ),
								)
							),
						),
					)
				),
				'expected_status'          => 200,
				'expected_code'            => null,
				'expected_incorrect_usage' => false,
			),
			'url_metrics_should_be_rejected_because_of_decreased_maximum_url_metrics_size' => array(
				'set_up'                   => static function (): void {
					add_filter(
						'od_maximum_url_metric_size',
						static function (): int {
							return MB_IN_BYTES / 2;
						}
					);
				},
				'params'                   => array_merge(
					$valid_params,
					array(
						// Fill the JSON with more than 1MB of data.
						'elements' => array(
							array_merge(
								$valid_element,
								array(
									'xpath' => sprintf( '/HTML/BODY/DIV[@id=\'%s\']/*[1][self::DIV]', str_repeat( 'A', MB_IN_BYTES ) ),
								)
							),
						),
					)
				),
				'expected_status'          => 413,
				'expected_code'            => 'rest_content_too_large',
				'expected_incorrect_usage' => false,
			),
			'negative_maximum_url_metric_size_is_treated_as_1mb' => array(
				'set_up'                   => static function (): void {
					add_filter(
						'od_maximum_url_metric_size',
						static function (): int {
							return -1;
						}
					);
				},
				'params'                   => array_merge(
					$valid_params,
					array(
						// Fill the JSON with more than 1MB of data.
						'elements' => array(
							array_merge(
								$valid_element,
								array(
									'xpath' => sprintf( '/HTML/BODY/DIV[@id=\'%s\']/*[1][self::DIV]', str_repeat( 'A', MB_IN_BYTES / 2 ) ),
								)
							),
						),
					)
				),
				'expected_status'          => 200,
				'expected_code'            => null,
				'expected_incorrect_usage' => true,
			),
		);
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
					'xpath' => '/HTML/BODY/DIV[@id=\'page\']/*[2][self::MAIN]/*[1][self::DIV]/*[1][self::FIGURE]/*[1][self::IMG]',
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
		$request = new WP_REST_Request( 'POST', $this->get_route() );
		$request->set_header( 'Content-Type', 'application/gzip' );
		$request->set_query_params( wp_array_slice_assoc( $params, array( 'hmac', 'current_etag', 'slug', 'cache_purge_post_id' ) ) );
		$request->set_header( 'Origin', home_url() );
		unset( $params['hmac'], $params['slug'], $params['current_etag'], $params['cache_purge_post_id'] );
		if ( ! function_exists( 'gzencode' ) ) {
			throw new Exception( 'The gzencode() function is not available.' );
		}
		$request->set_body( gzencode( wp_json_encode( $params ) ) );
		return $request;
	}
}
