<?php
/**
 * Tests for the Auto-sizes for Lazy-loaded Images plugin.
 *
 * @package auto-sizes
 * @group   auto-sizes
 */

class AutoSizesTests extends WP_UnitTestCase {

	/**
	 * Attachment ID.
	 *
	 * @var int
	 */
	public static $image_id;

	/**
	 * Setup shared fixtures.
	 */
	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$image_id = $factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leaves.jpg' );
	}

	/**
	 * Helper function to create image markup from a given attachment ID.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string Image markup.
	 */
	public function get_image_tag( $attachment_id ) {
		return get_image_tag( $attachment_id, '', '', '', 'large' );
	}

	public function test_hooks() {
		$this->assertSame( 10, has_filter( 'wp_get_attachment_image_attributes', 'auto_sizes_update_image_attributes' ) );
		$this->assertSame( 10, has_filter( 'wp_content_img_tag', 'auto_sizes_update_content_img_tag' ) );
		$this->assertSame( 10, has_action( 'wp_head', 'auto_sizes_render_generator' ) );
	}

	/**
	 * Test generated markup for an image with lazy loading gets auto-sizes.
	 *
	 * @covers ::auto_sizes_update_image_attributes
	 */
	public function test_image_with_lazy_loading_has_auto_sizes() {
		$this->assertStringContainsString(
			'sizes="auto, ',
			wp_get_attachment_image( self::$image_id, 'large', false, array( 'loading' => 'lazy' ) )
		);
	}

	/**
	 * Test generated markup for an image without lazy loading does not get auto-sizes.
	 *
	 * @covers ::auto_sizes_update_image_attributes
	 */
	public function test_image_without_lazy_loading_does_not_have_auto_sizes() {
		$this->assertStringContainsString(
			'sizes="(max-width: 1024px) 100vw, 1024px"',
			wp_get_attachment_image( self::$image_id, 'large', false, array( 'loading' => '' ) )
		);
	}

	/**
	 * Test content filtered markup with lazy loading gets auto-sizes.
	 *
	 * @covers ::auto_sizes_update_content_img_tag
	 */
	public function test_content_image_with_lazy_loading_has_auto_sizes() {
		// Force lazy loading attribute.
		add_filter( 'wp_img_tag_add_loading_attr', '__return_true' );

		$image_tag = $this->get_image_tag( self::$image_id );

		$this->assertStringContainsString(
			'sizes="auto, (max-width: 1024px) 100vw, 1024px"',
			wp_filter_content_tags( $this->get_image_tag( self::$image_id ) )
		);
	}

	/**
	 * Test content filtered markup without lazy loading does not get auto-sizes.
	 *
	 * @covers ::auto_sizes_update_content_img_tag
	 */
	public function test_content_image_without_lazy_loading_does_not_have_auto_sizes() {
		// Disable lazy loading attribute.
		add_filter( 'wp_img_tag_add_loading_attr', '__return_false' );

		$this->assertStringContainsString(
			'sizes="(max-width: 1024px) 100vw, 1024px"',
			wp_filter_content_tags( $this->get_image_tag( self::$image_id ) )
		);
	}

	/**
	 * Test printing the meta generator tag.
	 *
	 * @covers ::auto_sizes_render_generator
	 */
	public function test_auto_sizes_render_generator() {
		$tag = get_echo( 'auto_sizes_render_generator' );
		$this->assertStringStartsWith( '<meta', $tag );
		$this->assertStringContainsString( 'generator', $tag );
		$this->assertStringContainsString( 'auto-sizes ' . IMAGE_AUTO_SIZES_VERSION, $tag );
	}
}
