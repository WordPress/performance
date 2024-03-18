<?php
/**
 * Tests for optimization-detective module storage/post-type.php.
 *
 * @package optimization-detective
 *
 * @noinspection PhpUnhandledExceptionInspection
 */

class OD_Storage_Post_Type_Tests extends WP_UnitTestCase {

	/**
	 * Test od_register_url_metrics_post_type().
	 *
	 * @covers ::od_register_url_metrics_post_type
	 */
	public function test_od_register_url_metrics_post_type() {
		$this->assertSame( 10, has_action( 'init', 'od_register_url_metrics_post_type' ) );
		$post_type_object = get_post_type_object( OD_URL_METRICS_POST_TYPE );
		$this->assertInstanceOf( WP_Post_Type::class, $post_type_object );
		$this->assertFalse( $post_type_object->public );
	}

	/**
	 * Test od_get_url_metrics_post() when there is no post.
	 *
	 * @covers ::od_get_url_metrics_post
	 */
	public function test_od_get_url_metrics_post_when_absent() {
		$slug = od_get_url_metrics_slug( array( 'p' => '1' ) );
		$this->assertNull( od_get_url_metrics_post( $slug ) );
	}

	/**
	 * Test od_get_url_metrics_post() when there is a post.
	 *
	 * @covers ::od_get_url_metrics_post
	 */
	public function test_od_get_url_metrics_post_when_present() {
		$slug = od_get_url_metrics_slug( array( 'p' => '1' ) );

		$post_id = self::factory()->post->create(
			array(
				'post_type' => OD_URL_METRICS_POST_TYPE,
				'post_name' => $slug,
			)
		);

		$post = od_get_url_metrics_post( $slug );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( $post_id, $post->ID );
	}

	/**
	 * Data provider for test_od_parse_stored_url_metrics.
	 *
	 * @return array<string, array{post_content: string, expected_value: array}>
	 */
	public function data_provider_test_od_parse_stored_url_metrics(): array {
		$valid_content = array(
			array(
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
	 * Test od_parse_stored_url_metrics().
	 *
	 * @covers ::od_parse_stored_url_metrics
	 *
	 * @dataProvider data_provider_test_od_parse_stored_url_metrics
	 */
	public function test_od_parse_stored_url_metrics( string $post_content, array $expected_value ) {
		$post = self::factory()->post->create_and_get(
			array(
				'post_type'    => OD_URL_METRICS_POST_TYPE,
				'post_content' => $post_content,
			)
		);

		$url_metrics = array_map(
			static function ( OD_URL_Metric $url_metric ): array {
				return $url_metric->jsonSerialize();
			},
			od_parse_stored_url_metrics( $post )
		);

		$this->assertSame( $expected_value, $url_metrics );
	}

	/**
	 * Test od_store_url_metric().
	 *
	 * @covers ::od_store_url_metric
	 */
	public function test_od_store_url_metric() {
		$url  = home_url( '/' );
		$slug = od_get_url_metrics_slug( array( 'p' => 1 ) );

		$validated_url_metric = new OD_URL_Metric(
			array(
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

		$post_id = od_store_url_metric( $url, $slug, $validated_url_metric );
		$this->assertIsInt( $post_id );

		$post = od_get_url_metrics_post( $slug );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( $post_id, $post->ID );

		$url_metrics = od_parse_stored_url_metrics( $post );
		$this->assertCount( 1, $url_metrics );

		$again_post_id = od_store_url_metric( $url, $slug, $validated_url_metric );
		$post          = get_post( $again_post_id );
		$this->assertSame( $post_id, $again_post_id );
		$url_metrics = od_parse_stored_url_metrics( $post );
		$this->assertCount( 2, $url_metrics );
	}
}
