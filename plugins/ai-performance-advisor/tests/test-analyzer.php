<?php
/**
 * Tests for the analysis engine.
 *
 * @package ai-performance-advisor
 */

declare( strict_types = 1 );

require_once __DIR__ . '/class-aipa-fake-context-provider.php';

class AIPA_Test_Analyzer extends WP_UnitTestCase {

	/**
	 * Number of times the (mocked) model was asked to generate text.
	 *
	 * @var int
	 */
	private int $ai_calls = 0;

	public function set_up(): void {
		parent::set_up();
		$this->ai_calls = 0;
		delete_transient( AIPA_Analyzer::CACHE_KEY );
		add_action( 'aipa_register_context_providers', array( $this, 'use_hermetic_provider' ) );
	}

	public function tear_down(): void {
		delete_transient( AIPA_Analyzer::CACHE_KEY );
		remove_all_filters( 'aipa_pre_is_ai_available' );
		remove_all_filters( 'aipa_pre_generate_text' );
		remove_all_actions( 'aipa_register_context_providers' );
		parent::tear_down();
	}

	/**
	 * Replaces the default providers with a single hermetic one (no network/Site Health).
	 *
	 * @param AIPA_Context_Provider_Registry $registry The registry being assembled.
	 */
	public function use_hermetic_provider( AIPA_Context_Provider_Registry $registry ): void {
		foreach ( array( 'environment', 'site_health', 'site_health_tests', 'pagespeed', 'optimization_detective' ) as $key ) {
			$registry->unregister( $key );
		}
		$registry->register( new AIPA_Fake_Context_Provider() );
	}

	/**
	 * @covers AIPA_Analyzer
	 */
	public function test_analyze_returns_error_when_ai_unavailable(): void {
		add_filter( 'aipa_pre_is_ai_available', '__return_false' );

		$result = ( new AIPA_Analyzer() )->analyze();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'aipa_ai_unavailable', $result->get_error_code() );
	}

	/**
	 * @covers AIPA_Analyzer
	 */
	public function test_analyze_returns_sanitized_recommendations(): void {
		$this->force_available();
		$this->set_ai_response(
			(string) wp_json_encode(
				array(
					array(
						'title'    => 'Compress images',
						'summary'  => 'Serve images as WebP and size them correctly.',
						'severity' => 'critical',
						'category' => 'images',
					),
				)
			)
		);

		$result = ( new AIPA_Analyzer() )->analyze();

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertSame( 'Compress images', $result[0]['title'] );
		$this->assertSame( 'critical', $result[0]['severity'] );

		// The result is cached in a transient.
		$cached = get_transient( AIPA_Analyzer::CACHE_KEY );
		$this->assertIsArray( $cached );
		$this->assertArrayHasKey( 'hash', $cached );
		$this->assertArrayHasKey( 'recommendations', $cached );
	}

	/**
	 * @covers AIPA_Analyzer
	 */
	public function test_analyze_uses_cache_then_bypasses_with_refresh(): void {
		$this->force_available();
		$this->set_ai_response(
			(string) wp_json_encode(
				array(
					array(
						'title'   => 'First result',
						'summary' => 'From the first model call.',
					),
				)
			)
		);

		$analyzer = new AIPA_Analyzer();
		$first    = $analyzer->analyze();
		$this->assertSame( 1, $this->ai_calls );
		$this->assertIsArray( $first );
		$this->assertSame( 'First result', $first[0]['title'] );

		// A cached call with an unchanged context does not call the model again.
		$second = $analyzer->analyze( true );
		$this->assertSame( 1, $this->ai_calls );
		$this->assertIsArray( $second );
		$this->assertSame( 'First result', $second[0]['title'] );

		// Refreshing bypasses the cache and calls the model again.
		$third = $analyzer->analyze( false );
		$this->assertSame( 2, $this->ai_calls );
		$this->assertIsArray( $third );
		$this->assertSame( 'First result', $third[0]['title'] );
	}

	/**
	 * @covers AIPA_Analyzer
	 */
	public function test_analyze_parses_code_fenced_json(): void {
		$this->force_available();
		$this->set_ai_response( "```json\n[{\"title\":\"Enable caching\",\"summary\":\"Add a page cache.\"}]\n```" );

		$result = ( new AIPA_Analyzer() )->analyze();
		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertSame( 'Enable caching', $result[0]['title'] );
	}

	/**
	 * @covers AIPA_Analyzer
	 */
	public function test_analyze_parses_json_wrapped_in_prose(): void {
		$this->force_available();
		$this->set_ai_response( 'Here are the recommendations: [{"title":"Defer scripts","summary":"Defer non-critical JS."}] Hope this helps.' );

		$result = ( new AIPA_Analyzer() )->analyze();
		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertSame( 'Defer scripts', $result[0]['title'] );
	}

	/**
	 * @covers AIPA_Analyzer
	 */
	public function test_analyze_returns_empty_for_unparsable_response(): void {
		$this->force_available();
		$this->set_ai_response( 'totally not json' );

		$result = ( new AIPA_Analyzer() )->analyze();
		$this->assertSame( array(), $result );
	}

	/**
	 * @covers AIPA_Analyzer
	 */
	public function test_analyze_propagates_empty_response_error(): void {
		$this->force_available();
		$this->set_ai_response( '' );

		$result = ( new AIPA_Analyzer() )->analyze();
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'aipa_ai_empty_response', $result->get_error_code() );
	}

	/**
	 * @covers AIPA_Analyzer
	 */
	public function test_analyze_propagates_wp_error_from_model(): void {
		$this->force_available();
		add_filter(
			'aipa_pre_generate_text',
			static function () {
				return new WP_Error( 'provider_down', 'The provider is unavailable.' );
			}
		);

		$result = ( new AIPA_Analyzer() )->analyze();
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'provider_down', $result->get_error_code() );
	}

	/**
	 * @covers AIPA_Analyzer
	 */
	public function test_get_system_instruction_describes_output_contract(): void {
		$method = new ReflectionMethod( AIPA_Analyzer::class, 'get_system_instruction' );
		$method->setAccessible( true );
		$instruction = (string) $method->invoke( new AIPA_Analyzer() );

		$this->assertStringContainsString( 'JSON', $instruction );
		$this->assertStringContainsString( 'critical', $instruction );
		$this->assertStringContainsString( 'images', $instruction );
	}

	/**
	 * @covers AIPA_Analyzer
	 */
	public function test_request_returns_error_when_ai_client_throws(): void {
		if ( function_exists( 'wp_ai_client_prompt' ) ) {
			// The real AI Client API is present (WordPress >= 7.0), so the
			// missing-client failure path (an undefined-function Throwable) cannot be
			// exercised here. It is covered on environments without the AI Client.
			$this->assertTrue( function_exists( 'wp_ai_client_prompt' ) );
			return;
		}

		$this->force_available();
		// With no aipa_pre_generate_text filter, the analyzer calls the absent AI
		// client, which throws and is converted into a WP_Error.
		$result = ( new AIPA_Analyzer() )->analyze();
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'aipa_ai_request_failed', $result->get_error_code() );
	}

	/**
	 * Forces the AI availability check to report "available".
	 */
	private function force_available(): void {
		add_filter( 'aipa_pre_is_ai_available', '__return_true' );
	}

	/**
	 * Routes the model call through a canned response, counting invocations.
	 *
	 * @param string $response The canned model output.
	 */
	private function set_ai_response( string $response ): void {
		add_filter(
			'aipa_pre_generate_text',
			function () use ( $response ) {
				++$this->ai_calls;
				return $response;
			}
		);
	}
}
