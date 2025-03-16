<?php
/**
 * Tests for optimization-detective plugin detection.php.
 *
 * @package optimization-detective
 */

class Test_OD_Detection extends WP_UnitTestCase {

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
	 * Data provider.
	 *
	 * @return array<string, array{set_up: Closure, expected_is_query_object: bool, expected_query_object_class: string|null}>
	 */
	public function data_provider_od_get_cache_purge_post_id(): array {
		return array(
			'singular'  => array(
				'set_up'                      => function () {
					$post_id = self::factory()->post->create();
					$this->go_to( get_permalink( $post_id ) );
					return $post_id;
				},
				'expected_is_query_object'    => true,
				'expected_query_object_class' => WP_Post::class,
			),
			'home'      => array(
				'set_up'                      => function () {
					$post_id = self::factory()->post->create();
					$this->go_to( home_url() );
					return $post_id;
				},
				'expected_is_query_object'    => false,
				'expected_query_object_class' => null,
			),
			'category'  => array(
				'set_up'                      => function () {
					$cat_id = self::factory()->category->create();
					$post_id = self::factory()->post->create();
					wp_set_post_categories( $post_id, array( $cat_id ) );
					$this->go_to( get_category_link( $cat_id ) );
					return $post_id;
				},
				'expected_is_query_object'    => false,
				'expected_query_object_class' => WP_Term::class,
			),
			'not_found' => array(
				'set_up'                      => function () {
					$this->go_to( '/this-page-does-not-exist' );
					return null;
				},
				'expected_is_query_object'    => false,
				'expected_query_object_class' => null,
			),
		);
	}

	/**
	 * Tests od_get_cache_purge_post_id().
	 *
	 * @covers ::od_get_cache_purge_post_id
	 *
	 * @dataProvider data_provider_od_get_cache_purge_post_id
	 */
	public function test_od_get_cache_purge_post_id( Closure $set_up, bool $expected_is_query_object, ?string $expected_query_object_class ): void {
		$expected = $set_up();
		$this->assertSame( $expected, od_get_cache_purge_post_id() );
		if ( $expected_is_query_object ) {
			$this->assertSame( $expected, get_queried_object_id() );
		} else {
			$this->assertNotSame( $expected, get_queried_object_id() );
		}

		if ( null === $expected_query_object_class ) {
			$this->assertNull( get_queried_object() );
		} else {
			$this->assertSame( $expected_query_object_class, get_class( get_queried_object() ) );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{set_up: Closure, expected_exports: array<string, mixed>, expected_standard_build: bool}>
	 */
	public function data_provider_od_get_detection_script(): array {
		return array(
			'unfiltered'       => array(
				'set_up'                  => static function (): void {},
				'expected_exports'        => array(
					'storageLockTTL'      => MINUTE_IN_SECONDS,
					'extensionModuleUrls' => array(),
					'cachePurgePostId'    => null,
					'freshnessTTL'        => WEEK_IN_SECONDS,
					'gzdecodeAvailable'   => false,
				),
				'expected_standard_build' => true,
			),
			'unfiltered_admin' => array(
				'set_up'                  => static function (): void {
					wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
				},
				'expected_exports'        => array(
					'storageLockTTL'      => 0,
					'extensionModuleUrls' => array(),
					'cachePurgePostId'    => null,
				),
				'expected_standard_build' => true,
			),
			'filtered'         => array(
				'set_up'                  => static function (): void {
					add_filter(
						'od_url_metric_storage_lock_ttl',
						static function (): int {
							return HOUR_IN_SECONDS;
						}
					);
					add_filter(
						'od_extension_module_urls',
						static function ( array $urls ): array {
							$urls[] = home_url( '/my-extension.js', 'https' );
							return $urls;
						}
					);
					add_filter(
						'od_minimum_viewport_aspect_ratio',
						static function () {
							return 0;
						}
					);
					add_filter(
						'od_maximum_viewport_aspect_ratio',
						static function () {
							return 2;
						}
					);
					add_filter( 'od_use_web_vitals_attribution_build', '__return_true' );
					add_filter(
						'od_url_metric_storage_lock_ttl',
						static function () {
							return DAY_IN_SECONDS;
						}
					);
					add_filter(
						'od_url_metric_freshness_ttl',
						static function () {
							return WEEK_IN_SECONDS;
						}
					);
					add_filter( 'od_gzip_url_metric_store_request_payloads', '__return_true' );
				},
				'expected_exports'        => array(
					'storageLockTTL'         => DAY_IN_SECONDS,
					'freshnessTTL'           => WEEK_IN_SECONDS,
					'extensionModuleUrls'    => array( home_url( '/my-extension.js', 'https' ) ),
					'minViewportAspectRatio' => 0,
					'maxViewportAspectRatio' => 2,
					'gzdecodeAvailable'      => function_exists( 'gzencode' ),
				),
				'expected_standard_build' => false,
			),
		);
	}

	/**
	 * Make sure the expected script is printed.
	 *
	 * @covers ::od_get_detection_script
	 * @covers ::od_get_asset_path
	 * @covers OD_Storage_Lock::get_ttl
	 * @covers ::od_get_cache_purge_post_id
	 * @covers ::od_get_minimum_viewport_aspect_ratio
	 * @covers ::od_get_maximum_viewport_aspect_ratio
	 * @covers ::od_get_current_url
	 * @covers ::od_get_url_metrics_storage_hmac
	 * @covers OD_URL_Metric_Group::get_minimum_viewport_width
	 * @covers OD_URL_Metric_Group::is_complete
	 * @covers OD_URL_Metric_Group_Collection::get_current_etag
	 *
	 * @dataProvider data_provider_od_get_detection_script
	 *
	 * @param Closure               $set_up                  Set up callback.
	 * @param array<string, string> $expected_exports        Expected exports.
	 * @param bool                  $expected_standard_build Expected standard build.
	 */
	public function test_od_get_detection_script_returns_script( Closure $set_up, array $expected_exports, bool $expected_standard_build ): void {
		$set_up();
		$slug         = od_get_url_metrics_slug( array() );
		$current_etag = md5( '' );

		$expected_exports = array_merge(
			array(
				'minViewportAspectRatio' => od_get_minimum_viewport_aspect_ratio(),
				'maxViewportAspectRatio' => od_get_maximum_viewport_aspect_ratio(),
				'isDebug'                => WP_DEBUG,
				'currentUrl'             => od_get_current_url(),
				'urlMetricSlug'          => $slug,
				'cachePurgePostId'       => od_get_cache_purge_post_id(),
				'freshnessTTL'           => od_get_url_metric_freshness_ttl(),
			),
			$expected_exports
		);

		$breakpoints      = array( 480, 600, 782 );
		$group_collection = new OD_URL_Metric_Group_Collection( array(), $current_etag, $breakpoints, 3, HOUR_IN_SECONDS );

		$script = od_get_detection_script( $slug, $group_collection );

		$this->assertStringContainsString( '<script type="module">', $script );
		$this->assertStringContainsString( 'import detect from', $script );
		foreach ( $expected_exports as $key => $value ) {
			$this->assertStringContainsString( sprintf( '%s:%s', wp_json_encode( $key ), wp_json_encode( $value ) ), $script );
		}
		$this->assertStringContainsString( '"urlMetricHMAC":', $script );
		$this->assertSame( 1, preg_match( '/"webVitalsLibrarySrc":("[^"]+?")/', $script, $matches ) );
		$web_vitals_library_src = json_decode( $matches[1] );
		$this->assertStringContainsString(
			$expected_standard_build ? '/web-vitals.' : '/web-vitals-attribution.',
			$web_vitals_library_src
		);
		$this->assertStringContainsString( '"minimumViewportWidth":0', $script );
		$this->assertStringContainsString( '"minimumViewportWidth":480', $script );
		$this->assertStringContainsString( '"minimumViewportWidth":600', $script );
		$this->assertStringContainsString( '"minimumViewportWidth":782', $script );
		$this->assertStringContainsString( '"complete":false', $script );
		if ( is_user_logged_in() ) {
			$this->assertStringContainsString( '"restApiNonce":', $script );
		} else {
			$this->assertStringNotContainsString( '"restApiNonce":', $script );
		}
	}

	/**
	 * Test od_register_rest_url_metric_store_endpoint().
	 *
	 * @covers ::od_register_rest_url_metric_store_endpoint
	 * @covers OD_REST_URL_Metrics_Store_Endpoint::get_registration_args
	 */
	public function test_od_register_rest_url_metric_store_endpoint(): void {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/' . OD_REST_URL_Metrics_Store_Endpoint::ROUTE_NAMESPACE . OD_REST_URL_Metrics_Store_Endpoint::ROUTE_BASE, $routes );
	}

	/**
	 * Test od_trigger_post_update_actions().
	 *
	 * @covers ::od_trigger_post_update_actions
	 */
	public function test_trigger_page_cache_invalidation(): void {
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

		od_trigger_post_update_actions( $cache_purge_post_id );

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
	 * Test od_trigger_post_update_actions() for an invalid post.
	 *
	 * @covers ::od_trigger_post_update_actions
	 */
	public function test_od_trigger_post_update_actions(): void {
		wp_delete_post( 1, true );
		$before_clean_post_cache_count       = did_action( 'clean_post_cache' );
		$before_transition_post_status_count = did_action( 'transition_post_status' );
		$before_save_post_count              = did_action( 'save_post' );
		od_trigger_post_update_actions( 1 );
		$this->assertSame( $before_clean_post_cache_count, did_action( 'clean_post_cache' ) );
		$this->assertSame( $before_transition_post_status_count, did_action( 'transition_post_status' ) );
		$this->assertSame( $before_save_post_count, did_action( 'save_post' ) );
	}
}
