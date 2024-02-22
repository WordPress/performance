<?php
/**
 * Tests for image-loading-optimization module storage/post-type.php.
 *
 * @package performance-lab
 * @group   image-loading-optimization
 */

class ILO_Storage_Post_Type_Tests extends WP_UnitTestCase {

	/**
	 * Test ilo_register_url_metrics_post_type().
	 *
	 * @covers ::ilo_register_url_metrics_post_type
	 */
	public function test_ilo_register_url_metrics_post_type() {
		$this->assertSame( 10, has_action( 'init', 'ilo_register_url_metrics_post_type' ) );
		$post_type_object = get_post_type_object( ILO_URL_METRICS_POST_TYPE );
		$this->assertInstanceOf( WP_Post_Type::class, $post_type_object );
		$this->assertFalse( $post_type_object->public );
	}

	/**
	 * Test ilo_get_url_metrics_post() when there is no post.
	 *
	 * @covers ::ilo_get_url_metrics_post
	 */
	public function test_ilo_get_url_metrics_post_when_absent() {
		$slug = ilo_get_url_metrics_slug( array( 'p' => '1' ) );
		$this->assertNull( ilo_get_url_metrics_post( $slug ) );
	}

	/**
	 * Test ilo_get_url_metrics_post() when there is a post.
	 *
	 * @covers ::ilo_get_url_metrics_post
	 */
	public function test_ilo_get_url_metrics_post_when_present() {
		$slug = ilo_get_url_metrics_slug( array( 'p' => '1' ) );

		$post_id = self::factory()->post->create(
			array(
				'post_type' => ILO_URL_METRICS_POST_TYPE,
				'post_name' => $slug,
			)
		);

		$post = ilo_get_url_metrics_post( $slug );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( $post_id, $post->ID );
	}

	/**
	 * Data provider for test_ilo_parse_stored_url_metrics.
	 *
	 * @return array<string, array{post_content: string, expected_value: array}>
	 */
	public function data_provider_test_ilo_parse_stored_url_metrics(): array {
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
	 * Test ilo_parse_stored_url_metrics().
	 *
	 * @covers ::ilo_parse_stored_url_metrics
	 *
	 * @dataProvider data_provider_test_ilo_parse_stored_url_metrics
	 */
	public function test_ilo_parse_stored_url_metrics( string $post_content, array $expected_value ) {
		$post = self::factory()->post->create_and_get(
			array(
				'post_type'    => ILO_URL_METRICS_POST_TYPE,
				'post_content' => $post_content,
			)
		);

		$url_metrics = array_map(
			static function ( ILO_URL_Metric $url_metric ): array {
				return $url_metric->jsonSerialize();
			},
			ilo_parse_stored_url_metrics( $post )
		);

		$this->assertSame( $expected_value, $url_metrics );
	}

	/**
	 * Test ilo_store_url_metric().
	 *
	 * @covers ::ilo_store_url_metric
	 */
	public function test_ilo_store_url_metric() {
		$url  = home_url( '/' );
		$slug = ilo_get_url_metrics_slug( array( 'p' => 1 ) );

		$validated_url_metric = new ILO_URL_Metric(
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

		$post_id = ilo_store_url_metric( $url, $slug, $validated_url_metric );
		$this->assertIsInt( $post_id );

		$post = ilo_get_url_metrics_post( $slug );
		$this->assertInstanceOf( WP_Post::class, $post );
		$this->assertSame( $post_id, $post->ID );

		$url_metrics = ilo_parse_stored_url_metrics( $post );
		$this->assertCount( 1, $url_metrics );

		$again_post_id = ilo_store_url_metric( $url, $slug, $validated_url_metric );
		$post          = get_post( $again_post_id );
		$this->assertSame( $post_id, $again_post_id );
		$url_metrics = ilo_parse_stored_url_metrics( $post );
		$this->assertCount( 2, $url_metrics );
	}
}
