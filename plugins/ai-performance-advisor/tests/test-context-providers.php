<?php
/**
 * Tests for context providers and the registry.
 *
 * @package ai-performance-advisor
 */

declare( strict_types = 1 );

require_once __DIR__ . '/class-aipa-fake-context-provider.php';
require_once __DIR__ . '/class-aipa-empty-context-provider.php';
require_once __DIR__ . '/class-aipa-unavailable-context-provider.php';

class AIPA_Test_Context_Providers extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( 'aipa_settings' );
		delete_transient( AIPA_Provider_PageSpeed::CACHE_KEY );
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'aipa_context' );
		remove_all_filters( 'site_status_tests' );
		parent::tear_down();
	}

	/**
	 * @covers AIPA_Context_Provider
	 */
	public function test_base_provider_is_available_defaults_to_true(): void {
		$provider = new AIPA_Fake_Context_Provider();
		$this->assertTrue( $provider->is_available() );
		$this->assertSame( 'fake', $provider->get_key() );
		$this->assertSame( 'Fake provider', $provider->get_label() );
	}

	/**
	 * @covers AIPA_Provider_Environment
	 */
	public function test_environment_provider_shape(): void {
		$provider = new AIPA_Provider_Environment();
		$this->assertSame( 'environment', $provider->get_key() );
		$this->assertNotEmpty( $provider->get_label() );
		$this->assertTrue( $provider->is_available() );

		$data = $provider->collect();
		$this->assertArrayHasKey( 'wp_version', $data );
		$this->assertArrayHasKey( 'php_version', $data );
		$this->assertArrayHasKey( 'active_theme', $data );
		$this->assertArrayHasKey( 'active_plugins', $data );
		$this->assertIsArray( $data['active_plugins'] );
		$this->assertSame( PHP_VERSION, $data['php_version'] );
	}

	/**
	 * @covers AIPA_Provider_Site_Health
	 */
	public function test_site_health_provider_collects_and_excludes_private_fields(): void {
		$provider = new AIPA_Provider_Site_Health();
		$this->assertSame( 'site_health', $provider->get_key() );
		$data = $provider->collect();

		// No private secret should leak into the payload.
		$flat = (string) wp_json_encode( $data );
		if ( defined( 'AUTH_KEY' ) && '' !== AUTH_KEY ) {
			$this->assertStringNotContainsString( (string) AUTH_KEY, $flat );
		}
	}

	/**
	 * @covers AIPA_Provider_Site_Health
	 */
	public function test_site_health_flatten_section_handles_all_value_types(): void {
		$provider = new AIPA_Provider_Site_Health();
		$method   = new ReflectionMethod( AIPA_Provider_Site_Health::class, 'flatten_section' );
		$method->setAccessible( true );

		$section = array(
			'fields' => array(
				'secret'    => array(
					'label'   => 'Secret',
					'value'   => 'sensitive',
					'private' => true,
				),
				'flag'      => array(
					'label' => 'Flag',
					'value' => true,
				),
				'list'      => array(
					'label' => 'List',
					'value' => array( 'a', 'b' ),
				),
				'obj'       => array(
					'label' => 'Obj',
					'value' => new stdClass(),
				),
				'no_label'  => array(
					'value' => 'no-label-value',
				),
				'not_array' => 'scalar-field',
			),
		);

		$flattened = $method->invoke( $provider, $section );

		$this->assertArrayNotHasKey( 'Secret', $flattened );
		$this->assertSame( 'true', $flattened['Flag'] );
		$this->assertSame( wp_json_encode( array( 'a', 'b' ) ), $flattened['List'] );
		$this->assertSame( '', $flattened['Obj'] );
		$this->assertSame( 'no-label-value', $flattened['no_label'] );
		$this->assertArrayNotHasKey( 'scalar-field', $flattened );
	}

	/**
	 * @covers AIPA_Provider_Site_Health
	 */
	public function test_site_health_flatten_section_without_fields_returns_empty(): void {
		$provider = new AIPA_Provider_Site_Health();
		$method   = new ReflectionMethod( AIPA_Provider_Site_Health::class, 'flatten_section' );
		$method->setAccessible( true );
		$this->assertSame( array(), $method->invoke( $provider, array( 'no_fields_key' => true ) ) );
	}

	/**
	 * @covers AIPA_Provider_Site_Health_Tests
	 */
	public function test_site_health_tests_provider_runs_only_valid_direct_tests(): void {
		// Return only the fake tests (ignoring core tests, some of which make network
		// requests) so the provider's behavior can be asserted deterministically.
		add_filter(
			'site_status_tests',
			static function (): array {
				return array(
					'direct' => array(
						'aipa_good'        => array(
							'label' => 'Good',
							'test'  => static function (): array {
								return array(
									'label'       => 'All good <b>here</b>',
									'status'      => 'good',
									'description' => '<p>Nothing to do.</p>',
								);
							},
						),
						'aipa_noncallable' => array(
							'label' => 'Non callable',
							'test'  => 'aipa_definitely_not_a_callable_function',
						),
						'aipa_throws'      => array(
							'label' => 'Throws',
							'test'  => static function (): array {
								throw new RuntimeException( 'boom' );
							},
						),
						'aipa_nonarray'    => array(
							'label' => 'Non array',
							'test'  => static function () {
								return 'not-an-array';
							},
						),
						'aipa_notest'      => array(
							'label' => 'Missing test callback',
						),
					),
					'async'  => array(),
				);
			},
			99
		);

		$provider = new AIPA_Provider_Site_Health_Tests();
		$this->assertSame( 'site_health_tests', $provider->get_key() );
		$results = $provider->collect();

		$this->assertArrayHasKey( 'aipa_good', $results );
		$this->assertSame( 'good', $results['aipa_good']['status'] );
		$this->assertSame( 'All good here', $results['aipa_good']['label'] );
		$this->assertSame( 'Nothing to do.', $results['aipa_good']['description'] );

		$this->assertArrayNotHasKey( 'aipa_noncallable', $results );
		$this->assertArrayNotHasKey( 'aipa_throws', $results );
		$this->assertArrayNotHasKey( 'aipa_nonarray', $results );
		$this->assertArrayNotHasKey( 'aipa_notest', $results );
	}

	/**
	 * @covers AIPA_Provider_Optimization_Detective
	 */
	public function test_optimization_detective_provider(): void {
		$provider = new AIPA_Provider_Optimization_Detective();
		$this->assertSame( 'optimization_detective', $provider->get_key() );
		$this->assertNotEmpty( $provider->get_label() );

		if ( $provider->is_available() ) {
			$data = $provider->collect();
			$this->assertTrue( $data['active'] );
			$this->assertArrayHasKey( 'version', $data );
			$this->assertIsInt( $data['measured_url_count'] );
		} else {
			$this->assertFalse( $provider->is_available() );
		}
	}

	/**
	 * @covers AIPA_Provider_PageSpeed
	 */
	public function test_pagespeed_provider_availability_follows_setting(): void {
		$provider = new AIPA_Provider_PageSpeed();
		$this->assertSame( 'pagespeed', $provider->get_key() );

		update_option( 'aipa_settings', array( 'include_pagespeed' => true ) );
		$this->assertTrue( $provider->is_available() );

		update_option( 'aipa_settings', array( 'include_pagespeed' => false ) );
		$this->assertFalse( $provider->is_available() );
	}

	/**
	 * @covers AIPA_Provider_PageSpeed
	 */
	public function test_pagespeed_provider_compacts_successful_response(): void {
		$body = (string) wp_json_encode(
			array(
				'lighthouseResult' => array(
					'categories' => array(
						'performance' => array( 'score' => 0.42 ),
					),
					'audits'     => array(
						'largest-contentful-paint'  => array( 'displayValue' => '4.2 s' ),
						'cumulative-layout-shift'   => array( 'displayValue' => '0.05' ),
						'render-blocking-resources' => array(
							'title'        => 'Eliminate render-blocking resources',
							'displayValue' => 'Potential savings of 1,200 ms',
							'score'        => 0.1,
							'details'      => array( 'type' => 'opportunity' ),
						),
						'already-good-opportunity'  => array(
							'title'   => 'Already good',
							'score'   => 1.0,
							'details' => array( 'type' => 'opportunity' ),
						),
						'not-an-opportunity'        => array(
							'title'   => 'Diagnostic',
							'score'   => 0.1,
							'details' => array( 'type' => 'table' ),
						),
					),
				),
			)
		);

		$this->mock_http( 200, $body );

		$provider = new AIPA_Provider_PageSpeed();
		$data     = $provider->collect();

		$this->assertSame( 42, $data['performance_score'] );
		$this->assertSame( '4.2 s', $data['metrics']['largest-contentful-paint'] );
		$this->assertSame( '0.05', $data['metrics']['cumulative-layout-shift'] );
		$this->assertSame( home_url( '/' ), $data['url'] );
		$this->assertSame( 'mobile', $data['strategy'] );

		$titles = wp_list_pluck( $data['opportunities'], 'title' );
		$this->assertContains( 'Eliminate render-blocking resources', $titles );
		$this->assertNotContains( 'Already good', $titles );
		$this->assertNotContains( 'Diagnostic', $titles );

		// The result is cached, so a second call does not perform another request.
		$this->http_calls = 0;
		$cached           = $provider->collect();
		$this->assertSame( $data, $cached );
		$this->assertSame( 0, $this->http_calls );
	}

	/**
	 * @covers AIPA_Provider_PageSpeed
	 */
	public function test_pagespeed_provider_includes_api_key_in_request(): void {
		update_option(
			'aipa_settings',
			array(
				'include_pagespeed' => true,
				'pagespeed_api_key' => 'secret-key',
			)
		);
		$this->mock_http( 200, (string) wp_json_encode( array( 'lighthouseResult' => array( 'audits' => array() ) ) ) );

		( new AIPA_Provider_PageSpeed() )->collect();

		$this->assertStringContainsString( 'key=secret-key', $this->last_http_url );
	}

	/**
	 * @covers AIPA_Provider_PageSpeed
	 */
	public function test_pagespeed_provider_handles_http_error(): void {
		add_filter(
			'pre_http_request',
			static function () {
				return new WP_Error( 'http_request_failed', 'Network down' );
			}
		);
		$data = ( new AIPA_Provider_PageSpeed() )->collect();
		$this->assertArrayHasKey( 'error', $data );
		$this->assertStringContainsString( 'Network down', $data['error'] );
	}

	/**
	 * @covers AIPA_Provider_PageSpeed
	 */
	public function test_pagespeed_provider_handles_non_200(): void {
		$this->mock_http( 500, 'Server error' );
		$data = ( new AIPA_Provider_PageSpeed() )->collect();
		$this->assertArrayHasKey( 'error', $data );
		$this->assertStringContainsString( '500', $data['error'] );
	}

	/**
	 * @covers AIPA_Provider_PageSpeed
	 */
	public function test_pagespeed_provider_handles_unparsable_body(): void {
		$this->mock_http( 200, 'this is not json' );
		$data = ( new AIPA_Provider_PageSpeed() )->collect();
		$this->assertArrayHasKey( 'error', $data );
	}

	/**
	 * @covers AIPA_Provider_PageSpeed
	 */
	public function test_pagespeed_provider_returns_cached_value(): void {
		$cached = array(
			'performance_score' => 99,
			'metrics'           => array(),
			'opportunities'     => array(),
		);
		set_transient( AIPA_Provider_PageSpeed::CACHE_KEY, $cached, HOUR_IN_SECONDS );

		$this->http_calls = 0;
		add_filter(
			'pre_http_request',
			function () {
				++$this->http_calls;
				return new WP_Error( 'should_not_run', 'unexpected' );
			}
		);

		$data = ( new AIPA_Provider_PageSpeed() )->collect();
		$this->assertSame( $cached, $data );
		$this->assertSame( 0, $this->http_calls );
	}

	/**
	 * @covers AIPA_Context_Provider_Registry
	 */
	public function test_registry_register_unregister_and_available(): void {
		$registry = new AIPA_Context_Provider_Registry();
		$registry->register( new AIPA_Fake_Context_Provider() );
		$registry->register( new AIPA_Unavailable_Context_Provider() );

		$available = $registry->get_available_providers();
		$this->assertArrayHasKey( 'fake', $available );
		$this->assertArrayNotHasKey( 'unavailable', $available );

		$this->assertSame( array( 'Fake provider' ), $registry->get_available_labels() );

		$registry->unregister( 'fake' );
		$this->assertArrayNotHasKey( 'fake', $registry->get_available_providers() );
	}

	/**
	 * @covers AIPA_Context_Provider_Registry
	 */
	public function test_registry_collect_skips_empty_and_unavailable_and_applies_filter(): void {
		$registry = new AIPA_Context_Provider_Registry();
		$registry->register( new AIPA_Fake_Context_Provider() );
		$registry->register( new AIPA_Empty_Context_Provider() );
		$registry->register( new AIPA_Unavailable_Context_Provider() );

		$context = $registry->collect();
		$this->assertArrayHasKey( 'fake', $context );
		// A provider that returns no data contributes no key.
		$this->assertArrayNotHasKey( 'empty', $context );
		// An unavailable provider is never collected.
		$this->assertArrayNotHasKey( 'unavailable', $context );

		add_filter(
			'aipa_context',
			static function ( array $ctx ): array {
				$ctx['injected'] = array( 'hello' => 'world' );
				return $ctx;
			}
		);
		$filtered = $registry->collect();
		$this->assertArrayHasKey( 'injected', $filtered );
	}

	/**
	 * Number of mocked HTTP requests performed.
	 *
	 * @var int
	 */
	private int $http_calls = 0;

	/**
	 * The URL of the most recent mocked HTTP request.
	 *
	 * @var string
	 */
	private string $last_http_url = '';

	/**
	 * Intercepts outbound HTTP requests and returns a canned response.
	 *
	 * @param int    $code Response status code.
	 * @param string $body Response body.
	 */
	private function mock_http( int $code, string $body ): void {
		$this->http_calls    = 0;
		$this->last_http_url = '';
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $code, $body ) {
				++$this->http_calls;
				$this->last_http_url = (string) $url;
				return array(
					'headers'  => array(),
					'body'     => $body,
					'response' => array(
						'code'    => $code,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => '',
				);
			},
			10,
			3
		);
	}
}
