<?php
/**
 * Tests for image-loading-optimization module storage/data.php.
 *
 * @package performance-lab
 * @group   image-loading-optimization
 */

class Image_Loading_Optimization_Storage_Data_Tests extends WP_UnitTestCase {

	public function tear_down() {
		unset( $GLOBALS['wp_customize'] );
		parent::tear_down();
	}

	/**
	 * Test ilo_get_url_metric_freshness_ttl().
	 *
	 * @test
	 * @covers ::ilo_get_url_metric_freshness_ttl
	 */
	public function test_ilo_get_url_metric_freshness_ttl() {
		$this->assertSame( DAY_IN_SECONDS, ilo_get_url_metric_freshness_ttl() );

		add_filter(
			'ilo_url_metric_freshness_ttl',
			static function (): int {
				return HOUR_IN_SECONDS;
			}
		);

		$this->assertSame( HOUR_IN_SECONDS, ilo_get_url_metric_freshness_ttl() );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_provider_test_ilo_can_optimize_response(): array {
		return array(
			'homepage'           => array(
				'set_up'   => function () {
					$this->go_to( home_url( '/' ) );
				},
				'expected' => true,
			),
			'homepage_filtered'  => array(
				'set_up'   => function () {
					$this->go_to( home_url( '/' ) );
					add_filter( 'ilo_can_optimize_response', '__return_false' );
				},
				'expected' => false,
			),
			'search'             => array(
				'set_up'   => function () {
					self::factory()->post->create( array( 'post_title' => 'Hello' ) );
					$this->go_to( home_url( '?s=Hello' ) );
				},
				'expected' => false,
			),
			'customizer_preview' => array(
				'set_up'   => function () {
					$this->go_to( home_url( '/' ) );
					global $wp_customize;
					/** @noinspection PhpIncludeInspection */
					require_once ABSPATH . 'wp-includes/class-wp-customize-manager.php';
					$wp_customize = new WP_Customize_Manager();
					$wp_customize->start_previewing_theme();
				},
				'expected' => false,
			),
			'post_request'       => array(
				'set_up'   => function () {
					$this->go_to( home_url( '/' ) );
					$_SERVER['REQUEST_METHOD'] = 'POST';
				},
				'expected' => false,
			),
		);
	}

	/**
	 * Test ilo_can_optimize_response().
	 *
	 * @test
	 * @covers ::ilo_can_optimize_response
	 * @dataProvider data_provider_test_ilo_can_optimize_response
	 */
	public function test_ilo_can_optimize_response( Closure $set_up, bool $expected ) {
		$set_up();
		$this->assertSame( $expected, ilo_can_optimize_response() );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public function data_provider_test_ilo_get_normalized_query_vars(): array {
		return array(
			'homepage'     => array(
				'set_up' => function (): array {
					$this->go_to( home_url( '/' ) );
					return array();
				},
			),
			'post'         => array(
				'set_up' => function (): array {
					$post_id = self::factory()->post->create();
					$this->go_to( get_permalink( $post_id ) );
					return array( 'p' => (string) $post_id );
				},
			),
			'date-archive' => array(
				'set_up' => function (): array {
					$post_id = self::factory()->post->create();
					$date = get_post_datetime( $post_id );

					$this->go_to(
						add_query_arg(
							array(
								'day'      => $date->format( 'j' ),
								'year'     => $date->format( 'Y' ),
								'monthnum' => $date->format( 'm' ),
								'bogus'    => 'ignore me',
							),
							home_url()
						)
					);
					return array(
						'year'     => $date->format( 'Y' ),
						'monthnum' => $date->format( 'm' ),
						'day'      => $date->format( 'j' ),
					);
				},
			),
			'404'          => array(
				'set_up' => function () {
					$this->go_to( home_url( '/?p=1000000' ) );
					return array( 'error' => 404 );
				},
			),
		);
	}

	/**
	 * Test ilo_get_normalized_query_vars().
	 *
	 * @test
	 * @covers ::ilo_get_normalized_query_vars
	 * @dataProvider data_provider_test_ilo_get_normalized_query_vars
	 */
	public function test_ilo_get_normalized_query_vars( Closure $set_up ) {
		$expected = $set_up();
		$this->assertSame( $expected, ilo_get_normalized_query_vars() );
	}

	/**
	 * Test ilo_get_url_metrics_slug().
	 *
	 * @test
	 * @covers ::ilo_get_url_metrics_slug
	 */
	public function test_ilo_get_url_metrics_slug() {
		$first  = ilo_get_url_metrics_slug( array() );
		$second = ilo_get_url_metrics_slug( array( 'p' => 1 ) );
		$this->assertNotEquals( $second, $first );
		foreach ( array( $first, $second ) as $slug ) {
			$this->assertMatchesRegularExpression( '/^[0-9a-f]{32}$/', $slug );
		}
	}

	/**
	 * Test ilo_get_url_metrics_storage_nonce().
	 *
	 * @test
	 * @covers ::ilo_get_url_metrics_storage_nonce
	 * @covers ::ilo_verify_url_metrics_storage_nonce
	 */
	public function test_ilo_get_url_metrics_storage_nonce_and_ilo_verify_url_metrics_storage_nonce() {
		$user_id = self::factory()->user->create();

		$nonce_life_actions = array();
		add_filter(
			'nonce_life',
			static function ( int $life, string $action ) use ( &$nonce_life_actions ): int {
				$nonce_life_actions[] = $action;
				return $life;
			},
			10,
			2
		);

		// Create first nonce for unauthenticated user.
		$slug   = ilo_get_url_metrics_slug( array() );
		$nonce1 = ilo_get_url_metrics_storage_nonce( $slug );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{10}$/', $nonce1 );
		$this->assertSame( 1, ilo_verify_url_metrics_storage_nonce( $nonce1, $slug ) );
		$this->assertCount( 2, $nonce_life_actions );

		// Create second nonce for unauthenticated user.
		$nonce2 = ilo_get_url_metrics_storage_nonce( $slug );
		$this->assertSame( $nonce1, $nonce2 );
		$this->assertCount( 3, $nonce_life_actions );

		// Create third nonce, this time for authenticated user.
		wp_set_current_user( $user_id );
		$nonce3 = ilo_get_url_metrics_storage_nonce( $slug );
		$this->assertNotEquals( $nonce3, $nonce2 );
		$this->assertSame( 0, ilo_verify_url_metrics_storage_nonce( $nonce1, $slug ) );
		$this->assertSame( 1, ilo_verify_url_metrics_storage_nonce( $nonce3, $slug ) );
		$this->assertCount( 6, $nonce_life_actions );

		foreach ( $nonce_life_actions as $nonce_life_action ) {
			$this->assertSame( "store_url_metrics:{$slug}", $nonce_life_action );
		}
	}

	public function data_provider_sample_size_and_breakpoints(): array {
		return array(
			'3 sample size and 2 breakpoints' => array(
				'sample_size'     => 3,
				'breakpoints'     => array( 480, 782 ),
				'viewport_widths' => array( 400, 600, 800 ),
			),
			'1 sample size and 1 breakpoint'  => array(
				'sample_size'     => 1,
				'breakpoints'     => array( 480 ),
				'viewport_widths' => array( 400, 800 ),
			),
		);
	}

	/**
	 * Test ilo_unshift_url_metrics() and its use of ilo_group_url_metrics_by_breakpoint().
	 *
	 * @test
	 * @covers ::ilo_unshift_url_metrics
	 * @covers ::ilo_group_url_metrics_by_breakpoint
	 * @dataProvider data_provider_sample_size_and_breakpoints
	 */
	public function test_ilo_unshift_url_metrics_and_ilo_group_url_metrics_by_breakpoint( int $sample_size, array $breakpoints, array $viewport_widths ) {
		$old_timestamp = 1701978742;

		// Fully populate the sample size for the breakpoints.
		$all_url_metrics = array();
		foreach ( $viewport_widths as $viewport_width ) {
			for ( $i = 0; $i < $sample_size; $i++ ) {
				$url_metric                      = $this->get_validated_url_metric();
				$url_metric['viewport']['width'] = $viewport_width;

				$all_url_metrics = ilo_unshift_url_metrics(
					$all_url_metrics,
					$url_metric,
					$breakpoints,
					$sample_size
				);
			}
		}
		$max_possible_url_metrics_count = $sample_size * ( count( $breakpoints ) + 1 );
		$this->assertCount(
			$max_possible_url_metrics_count,
			$all_url_metrics,
			sprintf( 'Expected there to be exactly sample size (%d) times the number of breakpoint groups (which is %d + 1)', $sample_size, count( $breakpoints ) )
		);

		// Make sure that ilo_unshift_url_metrics() added a timestamp and then force them to all be old.
		$all_url_metrics = array_map(
			function ( $url_metric ) use ( $old_timestamp ) {
				$this->assertArrayHasKey( 'timestamp', $url_metric, 'Expected a timestamp to have been added to a URL metric.' );
				$url_metric['timestamp'] = $old_timestamp;
				return $url_metric;
			},
			$all_url_metrics
		);

		// Try adding one URL metric for each breakpoint group.
		foreach ( $viewport_widths as $viewport_width ) {
			$url_metric                      = $this->get_validated_url_metric();
			$url_metric['viewport']['width'] = $viewport_width;

			$all_url_metrics = ilo_unshift_url_metrics(
				$all_url_metrics,
				$url_metric,
				$breakpoints,
				$sample_size
			);
		}
		$this->assertCount(
			$max_possible_url_metrics_count,
			$all_url_metrics,
			'Expected the total count of URL metrics to not exceed the multiple of the sample size.'
		);
		$new_count = 0;
		foreach ( $all_url_metrics as $url_metric ) {
			if ( $url_metric['timestamp'] > $old_timestamp ) {
				++$new_count;
			}
		}
		$this->assertGreaterThan( 0, $new_count, 'Expected there to be at least one new URL metric.' );
		$this->assertSame( count( $viewport_widths ), $new_count, 'Expected the new URL metrics to all have been added.' );
	}

	/**
	 * Test ilo_get_breakpoint_max_widths().
	 *
	 * @test
	 * @covers ::ilo_get_breakpoint_max_widths
	 */
	public function test_ilo_get_breakpoint_max_widths() {
		$this->markTestIncomplete();
	}

	/**
	 * Test ilo_get_url_metrics_breakpoint_sample_size().
	 *
	 * @test
	 * @covers ::ilo_get_url_metrics_breakpoint_sample_size
	 */
	public function test_ilo_get_url_metrics_breakpoint_sample_size() {
		$this->markTestIncomplete();
	}

	/**
	 * Test ilo_get_lcp_elements_by_minimum_viewport_widths().
	 *
	 * @test
	 * @covers ::ilo_get_lcp_elements_by_minimum_viewport_widths
	 */
	public function test_ilo_get_lcp_elements_by_minimum_viewport_widths() {
		$this->markTestIncomplete();
	}

	/**
	 * Test ilo_get_needed_minimum_viewport_widths().
	 *
	 * @test
	 * @covers ::ilo_get_needed_minimum_viewport_widths
	 */
	public function test_ilo_get_needed_minimum_viewport_widths() {
		$this->markTestIncomplete();
	}

	/**
	 * Test ilo_needs_url_metric_for_breakpoint().
	 *
	 * @test
	 * @covers ::ilo_needs_url_metric_for_breakpoint
	 */
	public function test_ilo_needs_url_metric_for_breakpoint() {
		$this->markTestIncomplete();
	}

	private function get_validated_url_metric(): array {
		return array(
			'viewport' => array(
				'width'  => 480,
				'height' => 640,
			),
			'elements' => array(
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
