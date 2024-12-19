<?php
/**
 * Tests for image-prioritizer plugin helper.php.
 *
 * @package image-prioritizer
 *
 * @noinspection PhpUnhandledExceptionInspection
 */

class Test_Image_Prioritizer_Helper extends WP_UnitTestCase {
	use Optimization_Detective_Test_Helpers;

	/**
	 * Runs the routine before each test is executed.
	 */
	public function set_up(): void {
		parent::set_up();

		// Normalize the data for computing the current URL Metrics ETag to work around the issue where there is no
		// global variable storing the OD_Tag_Visitor_Registry instance along with any registered tag visitors, so
		// during set up we do not know what the ETag will look like. The current ETag is only established when
		// the output begins to be processed by od_optimize_template_output_buffer().
		add_filter( 'od_current_url_metrics_etag_data', '__return_empty_array' );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function data_provider_to_test_image_prioritizer_init(): array {
		return array(
			'with_old_version' => array(
				'version'  => '0.5.0',
				'expected' => false,
			),
			'with_new_version' => array(
				'version'  => '99.0.0',
				'expected' => true,
			),
		);
	}

	/**
	 * @covers ::image_prioritizer_init
	 * @dataProvider data_provider_to_test_image_prioritizer_init
	 */
	public function test_image_prioritizer_init( string $version, bool $expected ): void {
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'wp_head' );
		remove_all_actions( 'od_register_tag_visitors' );

		image_prioritizer_init( $version );

		$this->assertSame( ! $expected, has_action( 'admin_notices' ) );
		$this->assertSame( $expected ? 10 : false, has_action( 'wp_head', 'image_prioritizer_render_generator_meta_tag' ) );
		$this->assertSame( $expected ? 10 : false, has_action( 'od_register_tag_visitors', 'image_prioritizer_register_tag_visitors' ) );
	}

	/**
	 * Test printing the meta generator tag.
	 *
	 * @covers ::image_prioritizer_render_generator_meta_tag
	 */
	public function test_image_prioritizer_render_generator_meta_tag(): void {
		$function_name = 'image_prioritizer_render_generator_meta_tag';
		$this->assertSame( 10, has_action( 'wp_head', $function_name ) );
		$tag = get_echo( $function_name );
		$this->assertStringStartsWith( '<meta', $tag );
		$this->assertStringContainsString( 'generator', $tag );
		$this->assertStringContainsString( 'image-prioritizer ' . IMAGE_PRIORITIZER_VERSION, $tag );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_test_filter_tag_visitors(): array {
		$test_cases = array();
		foreach ( (array) glob( __DIR__ . '/test-cases/*.php' ) as $test_case ) {
			$name                = basename( $test_case, '.php' );
			$test_cases[ $name ] = require $test_case;
		}
		return $test_cases;
	}

	/**
	 * Test end-to-end.
	 *
	 * @covers ::image_prioritizer_register_tag_visitors
	 * @covers Image_Prioritizer_Tag_Visitor
	 * @covers Image_Prioritizer_Img_Tag_Visitor
	 * @covers Image_Prioritizer_Background_Image_Styled_Tag_Visitor
	 *
	 * @dataProvider data_provider_test_filter_tag_visitors
	 *
	 * @param callable        $set_up   Setup function.
	 * @param callable|string $buffer   Content before.
	 * @param callable|string $expected Expected content after.
	 */
	public function test_end_to_end( callable $set_up, $buffer, $expected ): void {
		$set_up( $this, $this::factory() );

		$buffer = is_string( $buffer ) ? $buffer : $buffer();
		$buffer = od_optimize_template_output_buffer( $buffer );
		$buffer = preg_replace_callback(
			':(<script type="module">)(.+?)(</script>):s',
			static function ( $matches ) {
				array_shift( $matches );
				if ( false !== strpos( $matches[1], 'import detect' ) ) {
					$matches[1] = '/* import detect ... */';
				} elseif ( false !== strpos( $matches[1], 'const lazyVideoObserver' ) ) {
					$matches[1] = '/* const lazyVideoObserver ... */';
				} elseif ( false !== strpos( $matches[1], 'const lazyBgImageObserver' ) ) {
					$matches[1] = '/* const lazyBgImageObserver ... */';
				}
				return implode( '', $matches );
			},
			$buffer
		);

		$expected = is_string( $expected ) ? $expected : $expected();

		$this->assertEquals(
			$this->remove_initial_tabs( $expected ),
			$this->remove_initial_tabs( $buffer ),
			"Buffer snapshot:\n$buffer"
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_test_auto_sizes(): array {
		$outside_viewport_rect = array_merge(
			$this->get_sample_dom_rect(),
			array(
				'top' => 1000,
			)
		);

		return array(
			// Note: The Image Prioritizer plugin removes the loading attribute, and so then Auto Sizes does not then add sizes=auto.
			'wrongly_lazy_responsive_img'       => array(
				'element_metrics' => array(
					'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
					'isLCP'             => false,
					'intersectionRatio' => 1,
				),
				'buffer'          => '<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy" srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="(max-width: 600px) 480px, 800px">',
				'expected'        => '<img data-od-removed-loading="lazy" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800"  srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="(max-width: 600px) 480px, 800px">',
			),

			'non_responsive_image'              => array(
				'element_metrics' => array(
					'xpath'              => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
					'isLCP'              => false,
					'intersectionRatio'  => 0,
					'intersectionRect'   => $outside_viewport_rect,
					'boundingClientRect' => $outside_viewport_rect,
				),
				'buffer'          => '<img src="https://example.com/foo.jpg" alt="Quux" width="1200" height="800" loading="lazy">',
				'expected'        => '<img src="https://example.com/foo.jpg" alt="Quux" width="1200" height="800" loading="lazy">',
			),

			'auto_sizes_added'                  => array(
				'element_metrics' => array(
					'xpath'              => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
					'isLCP'              => false,
					'intersectionRatio'  => 0,
					'intersectionRect'   => $outside_viewport_rect,
					'boundingClientRect' => $outside_viewport_rect,
				),
				'buffer'          => '<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy" srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="(max-width: 600px) 480px, 800px">',
				'expected'        => '<img data-od-replaced-sizes="(max-width: 600px) 480px, 800px" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy" srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="auto, (max-width: 600px) 480px, 800px">',
			),

			'auto_sizes_already_added'          => array(
				'element_metrics' => array(
					'xpath'              => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
					'isLCP'              => false,
					'intersectionRatio'  => 0,
					'intersectionRect'   => $outside_viewport_rect,
					'boundingClientRect' => $outside_viewport_rect,
				),
				'buffer'          => '<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy" srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="auto, (max-width: 600px) 480px, 800px">',
				'expected'        => '<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy" srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="auto, (max-width: 600px) 480px, 800px">',
			),

			// If Auto Sizes added the sizes=auto attribute but Image Prioritizer ended up removing it due to the image not being lazy-loaded, remove sizes=auto again.
			'wrongly_auto_sized_responsive_img' => array(
				'element_metrics' => array(
					'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
					'isLCP'             => false,
					'intersectionRatio' => 1,
				),
				'buffer'          => '<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy" srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="auto, (max-width: 600px) 480px, 800px">',
				'expected'        => '<img data-od-removed-loading="lazy" data-od-replaced-sizes="auto, (max-width: 600px) 480px, 800px" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800"  srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="(max-width: 600px) 480px, 800px">',
			),

			'wrongly_auto_sized_responsive_img_with_only_auto' => array(
				'element_metrics' => array(
					'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
					'isLCP'             => false,
					'intersectionRatio' => 1,
				),
				'buffer'          => '<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy" srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="auto">',
				'expected'        => '<img data-od-removed-loading="lazy" data-od-replaced-sizes="auto" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800"  srcset="https://example.com/foo-480w.jpg 480w, https://example.com/foo-800w.jpg 800w" sizes="">',
			),
		);
	}

	/**
	 * Test auto sizes.
	 *
	 * @covers Image_Prioritizer_Img_Tag_Visitor::__invoke
	 *
	 * @dataProvider data_provider_test_auto_sizes
	 * @phpstan-param array{ xpath: string, isLCP: bool, intersectionRatio: int } $element_metrics
	 */
	public function test_auto_sizes_end_to_end( array $element_metrics, string $buffer, string $expected ): void {
		$this->populate_url_metrics( array( $element_metrics ) );

		$html_start_doc = '<html lang="en"><head><meta charset="utf-8"><title>...</title></head><body>';
		$html_end_doc   = '</body></html>';

		$buffer = od_optimize_template_output_buffer( $html_start_doc . $buffer . $html_end_doc );
		$buffer = preg_replace( '#.+?<body[^>]*>#s', '', $buffer );
		$buffer = preg_replace( '#</body>.*$#s', '', $buffer );

		$this->assertEquals(
			$this->remove_initial_tabs( $expected ),
			$this->remove_initial_tabs( $buffer ),
			"Buffer snapshot:\n$buffer"
		);
	}

	/**
	 * Test image_prioritizer_register_tag_visitors.
	 *
	 * @covers ::image_prioritizer_register_tag_visitors
	 */
	public function test_image_prioritizer_register_tag_visitors(): void {
		$registry = new OD_Tag_Visitor_Registry();
		image_prioritizer_register_tag_visitors( $registry );
		$this->assertTrue( $registry->is_registered( 'image-prioritizer/img' ) );
		$this->assertTrue( $registry->is_registered( 'image-prioritizer/background-image' ) );
		$this->assertTrue( $registry->is_registered( 'image-prioritizer/video' ) );
	}

	/**
	 * Test image_prioritizer_filter_extension_module_urls.
	 *
	 * @covers ::image_prioritizer_filter_extension_module_urls
	 */
	public function test_image_prioritizer_filter_extension_module_urls(): void {
		$initial_modules  = array(
			home_url( '/module.js' ),
		);
		$filtered_modules = image_prioritizer_filter_extension_module_urls( $initial_modules );
		$this->assertCount( 2, $filtered_modules );
		$this->assertSame( $initial_modules[0], $filtered_modules[0] );
		$this->assertStringContainsString( 'detect.', $filtered_modules[1] );
	}

	/**
	 * Test image_prioritizer_add_element_item_schema_properties.
	 *
	 * @covers ::image_prioritizer_add_element_item_schema_properties
	 */
	public function test_image_prioritizer_add_element_item_schema_properties(): void {
		$initial_schema  = array(
			'foo' => array(
				'type' => 'string',
			),
		);
		$filtered_schema = image_prioritizer_add_element_item_schema_properties( $initial_schema );
		$this->assertCount( 2, $filtered_schema );
		$this->assertArrayHasKey( 'foo', $filtered_schema );
		$this->assertArrayHasKey( 'lcpElementExternalBackgroundImage', $filtered_schema );
		$this->assertSame( 'object', $filtered_schema['lcpElementExternalBackgroundImage']['type'] );
		$this->assertSameSets( array( 'url', 'id', 'tag', 'class' ), array_keys( $filtered_schema['lcpElementExternalBackgroundImage']['properties'] ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function data_provider_for_test_image_prioritizer_add_element_item_schema_properties_inputs(): array {
		return array(
			'bad_type'         => array(
				'input_value'        => 'not_an_object',
				'expected_exception' => 'OD_URL_Metric[lcpElementExternalBackgroundImage] is not of type object.',
				'output_value'       => null,
			),
			'missing_props'    => array(
				'input_value'        => array(),
				'expected_exception' => 'url is a required property of OD_URL_Metric[lcpElementExternalBackgroundImage].',
				'output_value'       => null,
			),
			'bad_url_protocol' => array(
				'input_value'        => array(
					'url'   => 'javascript:alert(1)',
					'tag'   => 'DIV',
					'id'    => null,
					'class' => null,
				),
				'expected_exception' => 'OD_URL_Metric[lcpElementExternalBackgroundImage][url] does not match pattern ^https?://.',
				'output_value'       => null,
			),
			'bad_url_format'   => array(
				'input_value'        => array(
					'url'   => 'https://not a valid URL!!!',
					'tag'   => 'DIV',
					'id'    => null,
					'class' => null,
				),
				'expected_exception' => null,
				'output_value'       => array(
					'url'   => 'https://not%20a%20valid%20URL!!!', // This is due to sanitize_url() being used in core. More validation is needed.
					'tag'   => 'DIV',
					'id'    => null,
					'class' => null,
				),
			),
			'bad_url_length'   => array(
				'input_value'        => array(
					'url'   => 'https://example.com/' . str_repeat( 'a', 501 ),
					'tag'   => 'DIV',
					'id'    => null,
					'class' => null,
				),
				'expected_exception' => 'OD_URL_Metric[lcpElementExternalBackgroundImage][url] must be at most 500 characters long.',
				'output_value'       => null,
			),
			'bad_null_tag'     => array(
				'input_value'        => array(
					'url'   => 'https://example.com/',
					'tag'   => null,
					'id'    => null,
					'class' => null,
				),
				'expected_exception' => 'OD_URL_Metric[lcpElementExternalBackgroundImage][tag] is not of type string.',
				'output_value'       => null,
			),
			'bad_format_tag'   => array(
				'input_value'        => array(
					'url'   => 'https://example.com/',
					'tag'   => 'bad tag name!!',
					'id'    => null,
					'class' => null,
				),
				'expected_exception' => 'OD_URL_Metric[lcpElementExternalBackgroundImage][tag] does not match pattern ^[a-zA-Z0-9\-]+\z.',
				'output_value'       => null,
			),
			'bad_length_tag'   => array(
				'input_value'        => array(
					'url'   => 'https://example.com/',
					'tag'   => str_repeat( 'a', 101 ),
					'id'    => null,
					'class' => null,
				),
				'expected_exception' => 'OD_URL_Metric[lcpElementExternalBackgroundImage][tag] must be at most 100 characters long.',
				'output_value'       => null,
			),
			'bad_type_id'      => array(
				'input_value'        => array(
					'url'   => 'https://example.com/',
					'tag'   => 'DIV',
					'id'    => array( 'bad' ),
					'class' => null,
				),
				'expected_exception' => 'OD_URL_Metric[lcpElementExternalBackgroundImage][id] is not of type string,null.',
				'output_value'       => null,
			),
			'bad_length_id'    => array(
				'input_value'        => array(
					'url'   => 'https://example.com/',
					'tag'   => 'DIV',
					'id'    => str_repeat( 'a', 101 ),
					'class' => null,
				),
				'expected_exception' => 'OD_URL_Metric[lcpElementExternalBackgroundImage][id] must be at most 100 characters long.',
				'output_value'       => null,
			),
			'bad_type_class'   => array(
				'input_value'        => array(
					'url'   => 'https://example.com/',
					'tag'   => 'DIV',
					'id'    => 'main',
					'class' => array( 'bad' ),
				),
				'expected_exception' => 'OD_URL_Metric[lcpElementExternalBackgroundImage][class] is not of type string,null.',
				'output_value'       => null,
			),
			'bad_length_class' => array(
				'input_value'        => array(
					'url'   => 'https://example.com/',
					'tag'   => 'DIV',
					'id'    => 'main',
					'class' => str_repeat( 'a', 501 ),
				),
				'expected_exception' => 'OD_URL_Metric[lcpElementExternalBackgroundImage][class] must be at most 500 characters long.',
				'output_value'       => null,
			),
			'ok_minimal'       => array(
				'input_value'        => array(
					'url'   => 'https://example.com/bg.jpg',
					'tag'   => 'DIV',
					'id'    => null,
					'class' => null,
				),
				'expected_exception' => null,
				'output_value'       => array(
					'url'   => 'https://example.com/bg.jpg',
					'tag'   => 'DIV',
					'id'    => null,
					'class' => null,
				),
			),
			'ok_maximal'       => array(
				'input_value'        => array(
					'url'   => 'https://example.com/' . str_repeat( 'a', 476 ) . '.jpg',
					'tag'   => str_repeat( 'a', 100 ),
					'id'    => str_repeat( 'b', 100 ),
					'class' => str_repeat( 'c', 500 ),
				),
				'expected_exception' => null,
				'output_value'       => array(
					'url'   => 'https://example.com/' . str_repeat( 'a', 476 ) . '.jpg',
					'tag'   => str_repeat( 'a', 100 ),
					'id'    => str_repeat( 'b', 100 ),
					'class' => str_repeat( 'c', 500 ),
				),
			),
		);
	}

	/**
	 * Test image_prioritizer_add_element_item_schema_properties for various inputs.
	 *
	 * @covers ::image_prioritizer_add_element_item_schema_properties
	 *
	 * @dataProvider data_provider_for_test_image_prioritizer_add_element_item_schema_properties_inputs
	 *
	 * @param mixed                     $input_value        Input value.
	 * @param string|null               $expected_exception Expected exception message.
	 * @param array<string, mixed>|null $output_value       Output value.
	 */
	public function test_image_prioritizer_add_element_item_schema_properties_inputs( $input_value, ?string $expected_exception, ?array $output_value ): void {
		$data                                      = $this->get_sample_url_metric( array() )->jsonSerialize();
		$data['lcpElementExternalBackgroundImage'] = $input_value;
		$exception_message                         = null;
		try {
			$url_metric = new OD_URL_Metric( $data );
		} catch ( OD_Data_Validation_Exception $e ) {
			$exception_message = $e->getMessage();
		}

		$this->assertSame(
			$expected_exception,
			$exception_message,
			isset( $url_metric ) ? 'Data: ' . wp_json_encode( $url_metric->jsonSerialize(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) : ''
		);
		if ( isset( $url_metric ) ) {
			$this->assertSame( $output_value, $url_metric->jsonSerialize()['lcpElementExternalBackgroundImage'] );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed>
	 */
	public function data_provider_to_test_image_prioritizer_validate_background_image_url(): array {
		return array(
			'bad_url_parse_error'         => array(
				'set_up'       => static function (): string {
					return 'https:///www.example.com';
				},
				'expect_error' => 'background_image_url_lacks_host',
			),
			'bad_url_no_host'             => array(
				'set_up'       => static function (): string {
					return '/foo/bar?baz=1';
				},
				'expect_error' => 'background_image_url_lacks_host',
			),

			'bad_url_disallowed_origin'   => array(
				'set_up'       => static function (): string {
					return 'https://bad.example.com/foo.jpg';
				},
				'expect_error' => 'disallowed_background_image_url_host',
			),

			'good_other_origin_via_allowed_http_origins_filter' => array(
				'set_up'       => static function (): string {
					$image_url = 'https://other-origin.example.com/foo.jpg';

					add_filter(
						'allowed_http_origins',
						static function ( array $allowed_origins ): array {
							$allowed_origins[] = 'https://other-origin.example.com';
							return $allowed_origins;
						}
					);

					add_filter(
						'pre_http_request',
						static function ( $pre, $parsed_args, $url ) use ( $image_url ) {
							if ( 'HEAD' !== $parsed_args['method'] || $image_url !== $url ) {
								return $pre;
							}
							return array(
								'headers'  => array(
									'content-type'   => 'image/jpeg',
									'content-length' => '288449',
								),
								'body'     => '',
								'response' => array(
									'code'    => 200,
									'message' => 'OK',
								),
							);
						},
						10,
						3
					);

					return $image_url;
				},
				'expect_error' => null,
			),

			'good_url_allowed_cdn_origin' => array(
				'set_up'       => function (): string {
					$attachment_id = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/data/images/car.jpeg' );
					$this->assertIsInt( $attachment_id );

					add_filter(
						'wp_get_attachment_image_src',
						static function ( $src ): array {
							$src[0] = preg_replace( '#^https?://#i', 'https://my-image-cdn.example.com/', $src[0] );
							return $src;
						}
					);

					$src = wp_get_attachment_image_src( $attachment_id, 'large' );
					$this->assertIsArray( $src );
					$this->assertStringStartsWith( 'https://my-image-cdn.example.com/', $src[0] );

					add_filter(
						'pre_http_request',
						static function ( $pre, $parsed_args, $url ) use ( $src ) {
							if ( 'HEAD' !== $parsed_args['method'] || $src[0] !== $url ) {
								return $pre;
							}
							return array(
								'headers'  => array(
									'content-type'   => 'image/jpeg',
									'content-length' => '288449',
								),
								'body'     => '',
								'response' => array(
									'code'    => 200,
									'message' => 'OK',
								),
							);
						},
						10,
						3
					);

					return $src[0];
				},
				'expect_error' => null,
			),

			'bad_not_found'               => array(
				'set_up'       => static function (): string {
					$image_url = home_url( '/bad.jpg' );

					add_filter(
						'pre_http_request',
						static function ( $pre, $parsed_args, $url ) use ( $image_url ) {
							if ( 'HEAD' !== $parsed_args['method'] || $image_url !== $url ) {
								return $pre;
							}
							return array(
								'headers'  => array(
									'content-type'   => 'text/html',
									'content-length' => 1000,
								),
								'body'     => '',
								'response' => array(
									'code'    => 404,
									'message' => 'Not Found',
								),
							);
						},
						10,
						3
					);

					return $image_url;
				},
				'expect_error' => 'background_image_response_not_ok',
			),

			'bad_content_type'            => array(
				'set_up'       => static function (): string {
					$video_url = home_url( '/bad.mp4' );

					add_filter(
						'pre_http_request',
						static function ( $pre, $parsed_args, $url ) use ( $video_url ) {
							if ( 'HEAD' !== $parsed_args['method'] || $video_url !== $url ) {
								return $pre;
							}
							return array(
								'headers'  => array(
									'content-type'   => 'video/mp4',
									'content-length' => '288449000',
								),
								'body'     => '',
								'response' => array(
									'code'    => 200,
									'message' => 'OK',
								),
							);
						},
						10,
						3
					);

					return $video_url;
				},
				'expect_error' => 'background_image_response_not_image',
			),

			'bad_content_length'          => array(
				'set_up'       => static function (): string {
					$image_url = home_url( '/massive-image.jpg' );

					add_filter(
						'pre_http_request',
						static function ( $pre, $parsed_args, $url ) use ( $image_url ) {
							if ( 'HEAD' !== $parsed_args['method'] || $image_url !== $url ) {
								return $pre;
							}
							return array(
								'headers'  => array(
									'content-type'   => 'image/jpeg',
									'content-length' => (string) ( 2 * MB_IN_BYTES + 1 ),
								),
								'body'     => '',
								'response' => array(
									'code'    => 200,
									'message' => 'OK',
								),
							);
						},
						10,
						3
					);

					return $image_url;
				},
				'expect_error' => 'background_image_content_length_too_large',
			),

			'bad_redirect'                => array(
				'set_up'       => static function (): string {
					$redirect_url = home_url( '/redirect.jpg' );

					add_filter(
						'pre_http_request',
						static function ( $pre, $parsed_args, $url ) use ( $redirect_url ) {
							if ( $redirect_url === $url ) {
								return new WP_Error( 'http_request_failed', 'Too many redirects.' );
							}
							return $pre;
						},
						10,
						3
					);

					return $redirect_url;
				},
				'expect_error' => 'http_request_failed',
			),

			'good_same_origin'            => array(
				'set_up'       => static function (): string {
					$image_url = home_url( '/good.jpg' );

					add_filter(
						'pre_http_request',
						static function ( $pre, $parsed_args, $url ) use ( $image_url ) {
							if ( 'HEAD' !== $parsed_args['method'] || $image_url !== $url ) {
								return $pre;
							}
							return array(
								'headers'  => array(
									'content-type'   => 'image/jpeg',
									'content-length' => '288449',
								),
								'body'     => '',
								'response' => array(
									'code'    => 200,
									'message' => 'OK',
								),
							);
						},
						10,
						3
					);

					return $image_url;
				},
				'expect_error' => null,
			),
		);
	}

	/**
	 * Tests image_prioritizer_validate_background_image_url().
	 *
	 * @covers ::image_prioritizer_validate_background_image_url
	 *
	 * @dataProvider data_provider_to_test_image_prioritizer_validate_background_image_url
	 */
	public function test_image_prioritizer_validate_background_image_url( Closure $set_up, ?string $expect_error ): void {
		$url      = $set_up();
		$validity = image_prioritizer_validate_background_image_url( $url );
		if ( null === $expect_error ) {
			$this->assertTrue( $validity );
		} else {
			$this->assertInstanceOf( WP_Error::class, $validity );
			$this->assertSame( $expect_error, $validity->get_error_code() );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed>
	 */
	public function data_provider_to_test_image_prioritizer_filter_rest_request_before_callbacks(): array {
		$get_sample_url_metric_data = function (): array {
			return $this->get_sample_url_metric( array() )->jsonSerialize();
		};

		$create_request = static function ( array $url_metric_data ): WP_REST_Request {
			$request = new WP_REST_Request( 'POST', '/' . OD_REST_API_NAMESPACE . OD_URL_METRICS_ROUTE );
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( wp_json_encode( $url_metric_data ) );
			return $request;
		};

		$bad_origin_data = array(
			'url'   => 'https://bad-origin.example.com/image.jpg',
			'tag'   => 'DIV',
			'id'    => null,
			'class' => null,
		);

		return array(
			'invalid_external_bg_image'                 => array(
				'set_up' => static function () use ( $get_sample_url_metric_data, $create_request, $bad_origin_data ): WP_REST_Request {
					$url_metric_data = $get_sample_url_metric_data();

					$url_metric_data['lcpElementExternalBackgroundImage'] = $bad_origin_data;
					$url_metric_data['viewport']['width']  = 10101;
					$url_metric_data['viewport']['height'] = 20202;
					return $create_request( $url_metric_data );
				},
				'assert' => function ( WP_REST_Request $request ): void {
					$this->assertArrayNotHasKey( 'lcpElementExternalBackgroundImage', $request );
					$this->assertSame(
						array(
							'width'  => 10101,
							'height' => 20202,
						),
						$request['viewport']
					);
				},
			),

			'valid_external_bg_image'                   => array(
				'set_up' => static function () use ( $get_sample_url_metric_data, $create_request ): WP_REST_Request {
					$url_metric_data = $get_sample_url_metric_data();
					$image_url = home_url( '/good.jpg' );

					add_filter(
						'pre_http_request',
						static function ( $pre, $parsed_args, $url ) use ( $image_url ) {
							if ( 'HEAD' !== $parsed_args['method'] || $image_url !== $url ) {
								return $pre;
							}
							return array(
								'headers'  => array(
									'content-type'   => 'image/jpeg',
									'content-length' => '288449',
								),
								'body'     => '',
								'response' => array(
									'code'    => 200,
									'message' => 'OK',
								),
							);
						},
						10,
						3
					);

					$url_metric_data['lcpElementExternalBackgroundImage'] = array(
						'url'   => $image_url,
						'tag'   => 'DIV',
						'id'    => null,
						'class' => null,
					);

					$url_metric_data['viewport']['width']  = 30303;
					$url_metric_data['viewport']['height'] = 40404;
					return $create_request( $url_metric_data );
				},
				'assert' => function ( WP_REST_Request $request ): void {
					$this->assertArrayHasKey( 'lcpElementExternalBackgroundImage', $request );
					$this->assertIsArray( $request['lcpElementExternalBackgroundImage'] );
					$this->assertSame(
						array(
							'url'   => home_url( '/good.jpg' ),
							'tag'   => 'DIV',
							'id'    => null,
							'class' => null,
						),
						$request['lcpElementExternalBackgroundImage']
					);
					$this->assertSame(
						array(
							'width'  => 30303,
							'height' => 40404,
						),
						$request['viewport']
					);
				},
			),

			'invalid_external_bg_image_uppercase_route' => array(
				'set_up' => static function () use ( $get_sample_url_metric_data, $create_request, $bad_origin_data ): WP_REST_Request {
					$request = $create_request(
						array_merge(
							$get_sample_url_metric_data(),
							array( 'lcpElementExternalBackgroundImage' => $bad_origin_data )
						)
					);
					$request->set_route( str_replace( 'store', 'STORE', $request->get_route() ) );
					return $request;
				},
				'assert' => function ( WP_REST_Request $request ): void {
					$this->assertArrayNotHasKey( 'lcpElementExternalBackgroundImage', $request );
				},
			),

			'invalid_external_bg_image_trailing_newline_route' => array(
				'set_up' => static function () use ( $get_sample_url_metric_data, $create_request, $bad_origin_data ): WP_REST_Request {
					$request = $create_request(
						array_merge(
							$get_sample_url_metric_data(),
							array( 'lcpElementExternalBackgroundImage' => $bad_origin_data )
						)
					);
					$request->set_route( $request->get_route() . "\n" );
					return $request;
				},
				'assert' => function ( WP_REST_Request $request ): void {
					$this->assertArrayNotHasKey( 'lcpElementExternalBackgroundImage', $request );
				},
			),

			'not_store_post_request'                    => array(
				'set_up' => static function () use ( $get_sample_url_metric_data, $create_request, $bad_origin_data ): WP_REST_Request {
					$request = $create_request(
						array_merge(
							$get_sample_url_metric_data(),
							array( 'lcpElementExternalBackgroundImage' => $bad_origin_data )
						)
					);
					$request->set_method( 'GET' );
					return $request;
				},
				'assert' => function ( WP_REST_Request $request ) use ( $bad_origin_data ): void {
					$this->assertArrayHasKey( 'lcpElementExternalBackgroundImage', $request );
					$this->assertSame( $bad_origin_data, $request['lcpElementExternalBackgroundImage'] );
				},
			),

			'not_store_request'                         => array(
				'set_up' => static function () use ( $get_sample_url_metric_data, $create_request ): WP_REST_Request {
					$url_metric_data = $get_sample_url_metric_data();
					$url_metric_data['lcpElementExternalBackgroundImage'] = 'https://totally-different.example.com/';
					$request = $create_request( $url_metric_data );
					$request->set_route( '/foo/v2/bar' );
					return $request;
				},
				'assert' => function ( WP_REST_Request $request ): void {
					$this->assertArrayHasKey( 'lcpElementExternalBackgroundImage', $request );
					$this->assertSame( 'https://totally-different.example.com/', $request['lcpElementExternalBackgroundImage'] );
				},
			),
		);
	}

	/**
	 * Tests image_prioritizer_filter_rest_request_before_callbacks().
	 *
	 * @dataProvider data_provider_to_test_image_prioritizer_filter_rest_request_before_callbacks
	 *
	 * @covers ::image_prioritizer_filter_rest_request_before_callbacks
	 * @covers ::image_prioritizer_validate_background_image_url
	 */
	public function test_image_prioritizer_filter_rest_request_before_callbacks( Closure $set_up, Closure $assert ): void {
		$request           = $set_up();
		$response          = new WP_REST_Response();
		$handler           = array();
		$filtered_response = image_prioritizer_filter_rest_request_before_callbacks( $response, $handler, $request );
		$this->assertSame( $response, $filtered_response );
		$assert( $request );
	}

	/**
	 * Test image_prioritizer_get_video_lazy_load_script.
	 *
	 * @covers ::image_prioritizer_get_video_lazy_load_script
	 * @covers ::image_prioritizer_get_asset_path
	 */
	public function test_image_prioritizer_get_video_lazy_load_script(): void {
		$this->assertStringContainsString( 'new IntersectionObserver', image_prioritizer_get_video_lazy_load_script() );
	}

	/**
	 * Test image_prioritizer_get_lazy_load_bg_image_script.
	 *
	 * @covers ::image_prioritizer_get_lazy_load_bg_image_script
	 * @covers ::image_prioritizer_get_asset_path
	 */
	public function test_image_prioritizer_get_lazy_load_bg_image_script(): void {
		$this->assertStringContainsString( 'new IntersectionObserver', image_prioritizer_get_lazy_load_bg_image_script() );
	}

	/**
	 * Test image_prioritizer_get_lazy_load_bg_image_stylesheet.
	 *
	 * @covers ::image_prioritizer_get_lazy_load_bg_image_stylesheet
	 * @covers ::image_prioritizer_get_asset_path
	 */
	public function test_image_prioritizer_get_lazy_load_bg_image_stylesheet(): void {
		$this->assertStringContainsString( '.od-lazy-bg-image', image_prioritizer_get_lazy_load_bg_image_stylesheet() );
	}
}
