<?php

/**
 * Tests for dominant-color module.
 *
 * @package performance-lab
 * @group dominant-color
 */
class Dominant_Color_Test extends WP_UnitTestCase {

	/**
	 * Tests dominant_color_metadata().
	 *
	 * @dataProvider provider_set_of_images
	 *
	 * @covers ::dominant_color_metadata
	 */
	public function test_dominant_color_metadata( $image_path, $expected_color, $expected_transparency, $color_is_light ) {
		// Non existing attachment.
		$dominant_color_metadata = dominant_color_metadata( array(), 1 );
		$this->assertEmpty( $dominant_color_metadata );

		// Creating attachment.
		$attachment_id           = $this->factory->attachment->create_upload_object( $image_path );
		$dominant_color_metadata = dominant_color_metadata( array(), $attachment_id );
		$this->assertArrayHasKey( 'dominant_color', $dominant_color_metadata );
		$this->assertNotEmpty( $dominant_color_metadata['dominant_color'] );
		$this->assertStringContainsString( $expected_color, $dominant_color_metadata['dominant_color'] );
	}

	/**
	 * Tests has_transparency_metadata().
	 *
	 * @dataProvider provider_set_of_images
	 *
	 * @covers ::has_transparency_metadata
	 */
	public function test_has_transparency_metadata( $image_path, $expected_color, $expected_transparency, $color_is_light ) {
		// Non existing attachment.
		$transparency_metadata = dominant_color_metadata( array(), 1 );
		$this->assertEmpty( $transparency_metadata );

		$attachment_id         = $this->factory->attachment->create_upload_object( $image_path );
		$transparency_metadata = dominant_color_metadata( array(), $attachment_id );
		$this->assertArrayHasKey( 'has_transparency', $transparency_metadata );
		if ( $expected_transparency ) {
			$this->assertTrue( $transparency_metadata['has_transparency'] );
		} else {
			$this->assertFalse( $transparency_metadata['has_transparency'] );
		}
	}

	/**
	 * Tests tag_add_adjust().
	 *
	 * @dataProvider provider_set_of_images
	 *
	 * @covers ::dominant_color_img_tag_add_dominant_color
	 */
	public function test_tag_add_adjust_to_image_attributes( $image_path, $expected_color, $expected_transparency, $color_is_light ) {
		$attachment_id = $this->factory->attachment->create_upload_object( $image_path );
		$image_meta    = wp_get_attachment_metadata( $attachment_id );

		// Testing tag_add_adjust() with image being lazy load.
		$filtered_image_mock_lazy_load = '<img loading="lazy" width="1024" height="727" class="test" src="http://localhost:8888/wp-content/uploads/2022/03/test.png" />';

		if ( isset( $image_meta['dominant_color'] ) ) {
			$filtered_image_tags_added = dominant_color_img_tag_add_dominant_color( $filtered_image_mock_lazy_load, 'the_content', $attachment_id );
			$this->assertStringContainsString( 'data-dominantColor="' . $expected_color . '"', $filtered_image_tags_added );
			$this->assertStringContainsString( 'data-has-transparency="' . json_encode( $expected_transparency ) . '"', $filtered_image_tags_added );
			$this->assertStringContainsString( 'style="--dominant-color: #' . $expected_color . ';"', $filtered_image_tags_added );

			// Dominant Color class added.
			$dominant_color_class = ( $color_is_light ) ? 'dominant-color-light' : 'dominant-color-dark';
			$this->assertStringContainsString( $dominant_color_class, $filtered_image_tags_added );

			// Testing tag_add_adjust() without lazy load.
			$filtered_image_mock_not_lazy_load = '<img width="1024" height="727" src="http://localhost:8888/wp-content/uploads/2022/03/test.png" />';
			$filtered_image_tags_added         = dominant_color_img_tag_add_dominant_color( $filtered_image_mock_not_lazy_load, 'the_content', $attachment_id );
			$this->assertStringContainsString( 'data-dominantColor="' . $expected_color . '"', $filtered_image_tags_added );
			$this->assertStringContainsString( 'data-has-transparency="' . json_encode( $expected_transparency ) . '"', $filtered_image_tags_added );
			$this->assertStringNotContainsString( 'style="--dominant-color:', $filtered_image_tags_added );
		}

		// Deactivate filter.
		add_filter( 'dominant_color_img_tag_add_dominant_color', '__return_false' );
		$filtered_image_tags_not_added = dominant_color_img_tag_add_dominant_color( $filtered_image_mock_lazy_load, 'the_content', $attachment_id );
		$this->assertEquals( $filtered_image_mock_lazy_load, $filtered_image_tags_not_added );
	}

	/**
	 * Data provider for different functions.
	 *
	 * @return array
	 */
	function provider_set_of_images() {
		return array(
			'white_jpg'  => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/white.jpg',
				'expected_color'        => 'ffffff',
				'expected_transparency' => false,
				'color_is_light'        => true,
			),
			'trans4_gif' => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/trans4.gif',
				'expected_color'        => '133f00',
				'expected_transparency' => true,
				'color_is_light'        => false,
			),
		);
	}

	/**
	 * Tests dominant_color_color_is_light().
	 *
	 * @dataProvider provider_set_of_colors
	 *
	 * @covers ::dominant_color_color_is_light
	 */
	public function test_color_is_light( $color, $expected_result ) {
		$this->assertEquals( $expected_result, dominant_color_color_is_light( $color ) );
	}

	/**
	 * Data provider for dominant_color_color_is_light.
	 *
	 * @return array
	 */
	function provider_set_of_colors() {
		return array(
			'ccc'               => array(
				'color'  => '#ccc',
				'result' => true,
			),
			'white'             => array(
				'color'  => '#ffffff',
				'result' => true,
			),
			'black'             => array(
				'color'  => '#000000',
				'result' => false,
			),
			'white_transparent' => array(
				'color'  => '#ffffff00',
				'result' => true,
			),
			'black_transparent' => array(
				'color'  => '#000000ff',
				'result' => false,
			),
		);
	}

}

