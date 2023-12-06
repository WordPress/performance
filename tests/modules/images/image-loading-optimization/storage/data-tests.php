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

	/**
	 * Test ilo_unshift_url_metrics().
	 *
	 * @test
	 * @covers ::ilo_unshift_url_metrics
	 */
	public function test_ilo_unshift_url_metrics() {
		$this->markTestIncomplete();
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
	 * Test ilo_group_url_metrics_by_breakpoint().
	 *
	 * @test
	 * @covers ::ilo_group_url_metrics_by_breakpoint
	 */
	public function test_ilo_group_url_metrics_by_breakpoint() {
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
}
