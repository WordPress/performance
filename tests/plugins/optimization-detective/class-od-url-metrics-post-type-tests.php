<?php
/**
 * Tests for optimization-detective module storage/post-type.php.
 *
 * @package optimization-detective
 *
 * @coversDefaultClass OD_URL_Metrics_Post_Type
 * @noinspection PhpUnhandledExceptionInspection
 */

class OD_Storage_Post_Type_Tests extends WP_UnitTestCase {

	/**
	 * Test register().
	 *
	 * @covers ::register
	 */
	public function test_register() {
		$this->assertSame(
			10,
			has_action(
				'init',
				array(
					OD_URL_Metrics_Post_Type::class,
					'register',
				)
			)
		);
		$post_type_object = get_post_type_object( OD_URL_Metrics_Post_Type::SLUG );
		$this->assertInstanceOf( WP_Post_Type::class, $post_type_object );
		$this->assertFalse( $post_type_object->public );
	}

	/**
	 * Test get_post() when there is no post.
	 *
	 * @covers ::get_post
	 */
	public function test_od_post_when_absent() {
		$slug = od_get_url_metrics_slug( array( 'p' => '1' ) );
		$this->assertNull( OD_URL_Metrics_Post_Type::get_post( $slug ) );
	}

	/**
	 * Test get_post() when there is a post.
	 *
	 * @covers ::get_post
	 */
	public function test_od_post_when_present() {
		$slug = od_get_url_metrics_slug( array( 'p' => '1' ) );

		$post_id = self::factory()->post->create(
			array(
				'post_type' => OD_URL_Metrics_Post_Type::SLUG,
				'post_name' => $slug,
			)
		);

		$post = OD_URL_Metrics_Post_Type::get_post( $slug );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( $post_id, $post->ID );
	}

	/**
	 * Data provider for test_parse_post_content.
	 *
	 * @return array<string, array{post_content: string, expected_value: array}>
	 */
	public function data_provider_test_parse_post_content(): array {
		$valid_content = array(
			array(
				'url'       => home_url( '/' ),
				'viewport'  => array(
					'width'  => 640,
					'height' => 480,
				),
				'timestamp' => (int) microtime( true ), // Integer to facilitate equality tests.
				'elements'  => array(),
			),
		);

		return array(
			'malformed_json' => array(
				'post_content'   => '{"bad":',
				'expected_value' => array(),
			),
			'not_array_json' => array(
				'post_content'   => '{"cool":"beans"}',
				'expected_value' => array(),
			),
			'missing_keys'   => array(
				'post_content'   => '[{},{},{}]',
				'expected_value' => array(),
			),
			'valid'          => array(
				'post_content'   => wp_json_encode( $valid_content ),
				'expected_value' => $valid_content,
			),
		);
	}

	/**
	 * Test parse_post_content().
	 *
	 * @covers ::parse_post_content
	 *
	 * @dataProvider data_provider_test_parse_post_content
	 */
	public function test_parse_post_content( string $post_content, array $expected_value ) {
		$post = self::factory()->post->create_and_get(
			array(
				'post_type'    => OD_URL_Metrics_Post_Type::SLUG,
				'post_content' => $post_content,
			)
		);

		$url_metrics = array_map(
			static function ( OD_URL_Metric $url_metric ): array {
				return $url_metric->jsonSerialize();
			},
			OD_URL_Metrics_Post_Type::parse_post_content( $post )
		);

		$this->assertSame( $expected_value, $url_metrics );
	}

	/**
	 * Test store_url_metric().
	 *
	 * @covers ::store_url_metric
	 */
	public function test_store_url_metric() {
		$slug = od_get_url_metrics_slug( array( 'p' => 1 ) );

		$validated_url_metric = $this->get_sample_url_metric( home_url( '/' ) );

		$post_id = OD_URL_Metrics_Post_Type::store_url_metric( $slug, $validated_url_metric );
		$this->assertIsInt( $post_id );

		$post = OD_URL_Metrics_Post_Type::get_post( $slug );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( $post_id, $post->ID );

		$url_metrics = OD_URL_Metrics_Post_Type::parse_post_content( $post );
		$this->assertCount( 1, $url_metrics );

		$again_post_id = OD_URL_Metrics_Post_Type::store_url_metric( $slug, $validated_url_metric );
		$post          = get_post( $again_post_id );
		$this->assertSame( $post_id, $again_post_id );
		$url_metrics = OD_URL_Metrics_Post_Type::parse_post_content( $post );
		$this->assertCount( 2, $url_metrics );
	}

	/**
	 * Test delete_all_posts()
	 *
	 * @covers ::delete_all_posts
	 */
	public function test_delete_all_posts() {
		global $wpdb;

		$other_post_meta_key       = 'foo';
		$other_post_meta_value     = 'bar';
		$url_metrics_post_meta_key = 'baz';

		// Create sample posts of all post types other than URL Metrics.
		$other_post_ids = array();
		foreach ( array_diff( get_post_types(), array( OD_URL_Metrics_Post_Type::SLUG ) ) as $post_type ) {
			$other_post_ids = array_merge(
				$other_post_ids,
				self::factory()->post->create_many( 10, compact( 'post_type' ) )
			);
		}
		foreach ( $other_post_ids as $post_id ) {
			update_post_meta( $post_id, $other_post_meta_key, $other_post_meta_value );
		}

		// Now create sample URL Metrics posts.
		for ( $i = 1; $i <= 101; $i++ ) {
			$slug    = od_get_url_metrics_slug( array( 'p' => $i ) );
			$post_id = OD_URL_Metrics_Post_Type::store_url_metric( $slug, $this->get_sample_url_metric( home_url( "/?p=$i" ) ) );
			update_post_meta( $post_id, $url_metrics_post_meta_key, '' );
		}

		$get_post_type_counts = static function (): array {
			$post_type_counts = array();
			foreach ( get_post_types() as $post_type ) {
				$post_type_counts[ $post_type ] = (array) wp_count_posts( $post_type );
			}
			return $post_type_counts;
		};

		// Capture the initial post type counts.
		$initial_post_counts = $get_post_type_counts();
		$this->assertEquals( 10, $initial_post_counts['post']['publish'] );
		$this->assertEquals( 10, $initial_post_counts['page']['publish'] );
		$this->assertEquals( 101, $initial_post_counts[ OD_URL_Metrics_Post_Type::SLUG ]['publish'] );
		$other_post_meta_count = $wpdb->get_var( $wpdb->prepare( "SELECT count(*) FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s", $other_post_meta_key, $other_post_meta_value ) );
		$this->assertGreaterThan( 0, $other_post_meta_count );
		$this->assertEquals( 101, $wpdb->get_var( $wpdb->prepare( "SELECT count(*) FROM $wpdb->postmeta WHERE meta_key = %s", $url_metrics_post_meta_key ) ) );

		// Delete the URL Metrics posts.
		OD_URL_Metrics_Post_Type::delete_all_posts();

		wp_cache_flush();

		// Make sure that the counts are as expected.
		$final_post_counts = $get_post_type_counts();
		$this->assertEquals( 10, $final_post_counts['post']['publish'] );
		$this->assertEquals( 10, $final_post_counts['page']['publish'] );
		$this->assertEquals( 0, $final_post_counts[ OD_URL_Metrics_Post_Type::SLUG ]['publish'] );
		$initial_post_counts[ OD_URL_Metrics_Post_Type::SLUG ]['publish'] = 0;
		$this->assertEquals( $initial_post_counts, $final_post_counts );

		// Make sure post meta is intact.
		foreach ( $other_post_ids as $post_id ) {
			$this->assertInstanceOf( WP_Post::class, get_post( $post_id ) );
			$this->assertSame( $other_post_meta_value, get_post_meta( $post_id, $other_post_meta_key, true ) );
		}
		$this->assertEquals( 0, $wpdb->get_var( $wpdb->prepare( "SELECT count(*) FROM $wpdb->postmeta WHERE meta_key = %s", $url_metrics_post_meta_key ) ) );
		$this->assertEquals( $other_post_meta_count, $wpdb->get_var( $wpdb->prepare( "SELECT count(*) FROM $wpdb->postmeta WHERE meta_key = %s and meta_value = %s", $other_post_meta_key, $other_post_meta_value ) ) );
	}

	/**
	 * Gets a sample URL Metric.
	 *
	 * @param string $url URL.
	 *
	 * @return OD_URL_Metric
	 * @throws OD_Data_Validation_Exception When invalid data (but there won't be).
	 */
	private function get_sample_url_metric( string $url ): OD_URL_Metric {
		return new OD_URL_Metric(
			array(
				'url'       => $url,
				'viewport'  => array(
					'width'  => 480,
					'height' => 640,
				),
				'timestamp' => microtime( true ),
				'elements'  => array(
					array(
						'isLCP'             => true,
						'isLCPCandidate'    => true,
						'xpath'             => '/*[0][self::HTML]/*[1][self::BODY]/*[0][self::DIV]/*[1][self::MAIN]/*[0][self::DIV]/*[0][self::FIGURE]/*[0][self::IMG]',
						'intersectionRatio' => 1,
					),
				),
			)
		);
	}
}
