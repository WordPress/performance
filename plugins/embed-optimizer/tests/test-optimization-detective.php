<?php
/**
 * Tests for embed-optimizer plugin hooks.php.
 *
 * @package embed-optimizer
 */

/**
 * @phpstan-type ElementDataSubset array{xpath: string, isLCP: bool, intersectionRatio: float}
 */
class Test_Embed_Optimizer_Optimization_Detective extends WP_UnitTestCase {
	/**
	 * Runs the routine before each test is executed.
	 */
	public function set_up(): void {
		parent::set_up();
		if ( ! defined( 'OPTIMIZATION_DETECTIVE_VERSION' ) ) {
			$this->markTestSkipped( 'Optimization Detective is not active.' );
		}
	}

	/**
	 * Tests embed_optimizer_register_tag_visitors().
	 *
	 * @covers ::embed_optimizer_register_tag_visitors
	 */
	public function test_embed_optimizer_register_tag_visitors(): void {
		$registry = new OD_Tag_Visitor_Registry();
		embed_optimizer_register_tag_visitors( $registry );
		$this->assertTrue( $registry->is_registered( 'embeds' ) );
		$this->assertInstanceOf( Embed_Optimizer_Tag_Visitor::class, $registry->get_registered( 'embeds' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_test_od_optimize_template_output_buffer(): array {
		$test_cases = array();
		foreach ( (array) glob( __DIR__ . '/test-cases/*.php' ) as $test_case ) {
			$name                = basename( $test_case, '.php' );
			$test_cases[ $name ] = require $test_case;
		}
		return $test_cases;
	}

	/**
	 * Test embed_optimizer_visit_tag().
	 *
	 * @covers Embed_Optimizer_Tag_Visitor
	 * @covers ::embed_optimizer_update_markup
	 *
	 * @dataProvider data_provider_test_od_optimize_template_output_buffer
	 * @throws Exception But it won't.
	 */
	public function test_od_optimize_template_output_buffer( Closure $set_up, string $buffer, string $expected ): void {
		$set_up( $this );

		$remove_initial_tabs = static function ( string $input ): string {
			return (string) preg_replace( '/^\t+/m', '', $input );
		};

		$expected = $remove_initial_tabs( $expected );
		$buffer   = $remove_initial_tabs( $buffer );

		$buffer = od_optimize_template_output_buffer( $buffer );
		$buffer = preg_replace_callback(
			':(<script type="module">)(.+?)(</script>):s',
			static function ( $matches ) {
				array_shift( $matches );
				if ( false !== strpos( $matches[1], 'import detect' ) ) {
					$matches[1] = '/* import detect ... */';
				} elseif ( false !== strpos( $matches[1], 'const lazyEmbedsScripts' ) ) {
					$matches[1] = '/* const lazyEmbedsScripts ... */';
				}
				return implode( '', $matches );
			},
			$buffer
		);
		$this->assertEquals( $expected, $buffer );
	}

	/**
	 * Populates complete URL metrics for the provided element data.
	 *
	 * @phpstan-param ElementDataSubset[] $elements
	 * @param array[] $elements Element data.
	 * @param bool    $complete Whether to fully populate the groups.
	 * @throws Exception But it won't.
	 */
	public function populate_url_metrics( array $elements, bool $complete = true ): void {
		$slug        = od_get_url_metrics_slug( od_get_normalized_query_vars() );
		$sample_size = $complete ? od_get_url_metrics_breakpoint_sample_size() : 1;
		foreach ( array_merge( od_get_breakpoint_max_widths(), array( 1000 ) ) as $viewport_width ) {
			for ( $i = 0; $i < $sample_size; $i++ ) {
				OD_URL_Metrics_Post_Type::store_url_metric(
					$slug,
					$this->get_validated_url_metric(
						$viewport_width,
						$elements
					)
				);
			}
		}
	}

	/**
	 * Gets a validated URL metric.
	 *
	 * @param int                      $viewport_width Viewport width for the URL metric.
	 * @param array<ElementDataSubset> $elements       Elements.
	 * @return OD_URL_Metric URL metric.
	 * @throws Exception From OD_URL_Metric if there is a parse error, but there won't be.
	 */
	public function get_validated_url_metric( int $viewport_width, array $elements = array() ): OD_URL_Metric {
		$data = array(
			'url'       => home_url( '/' ),
			'viewport'  => array(
				'width'  => $viewport_width,
				'height' => 800,
			),
			'timestamp' => microtime( true ),
			'elements'  => array_map(
				static function ( array $element ): array {
					return array_merge(
						array(
							'isLCPCandidate'     => true,
							'intersectionRatio'  => 1,
							'intersectionRect'   => array(
								'width'  => 100,
								'height' => 100,
							),
							'boundingClientRect' => array(
								'width'  => 100,
								'height' => 100,
							),
						),
						$element
					);
				},
				$elements
			),
		);
		return new OD_URL_Metric( $data );
	}
}
