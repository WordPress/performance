<?php
/**
 * Tests for optimization-detective plugin detection.php.
 *
 * @package optimization-detective
 */

class Test_OD_Detection extends WP_UnitTestCase {

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
	 * @return array<string, array{set_up: Closure, expected_exports: array<string, mixed>}>
	 */
	public function data_provider_od_get_detection_script(): array {
		return array(
			'unfiltered' => array(
				'set_up'           => static function (): void {},
				'expected_exports' => array(
					'storageLockTTL'      => MINUTE_IN_SECONDS,
					'extensionModuleUrls' => array(),
				),
			),
			'filtered'   => array(
				'set_up'           => static function (): void {
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
				},
				'expected_exports' => array(
					'storageLockTTL'      => HOUR_IN_SECONDS,
					'extensionModuleUrls' => array( home_url( '/my-extension.js', 'https' ) ),
				),
			),
		);
	}

	/**
	 * Make sure the expected script is printed.
	 *
	 * @covers ::od_get_detection_script
	 *
	 * @dataProvider data_provider_od_get_detection_script
	 *
	 * @param Closure                                                                       $set_up           Set up callback.
	 * @param array<string, array{set_up: Closure, expected_exports: array<string, mixed>}> $expected_exports Expected exports.
	 */
	public function test_od_get_detection_script_returns_script( Closure $set_up, array $expected_exports ): void {
		$set_up();
		$slug         = od_get_url_metrics_slug( array( 'p' => '1' ) );
		$current_etag = md5( '' );

		$breakpoints      = array( 480, 600, 782 );
		$group_collection = new OD_URL_Metric_Group_Collection( array(), $current_etag, $breakpoints, 3, HOUR_IN_SECONDS );

		$script = od_get_detection_script( $slug, $group_collection );

		$this->assertStringContainsString( '<script type="module">', $script );
		$this->assertStringContainsString( 'import detect from', $script );
		foreach ( $expected_exports as $key => $value ) {
			$this->assertStringContainsString( sprintf( '%s:%s', wp_json_encode( $key ), wp_json_encode( $value ) ), $script );
		}
		$this->assertStringContainsString( '"minimumViewportWidth":0', $script );
		$this->assertStringContainsString( '"minimumViewportWidth":481', $script );
		$this->assertStringContainsString( '"minimumViewportWidth":601', $script );
		$this->assertStringContainsString( '"minimumViewportWidth":783', $script );
		$this->assertStringContainsString( '"complete":false', $script );
	}
}
