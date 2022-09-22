<?php

/**
 * Tests the adding of fetchpriority to img tags in the content.
 *
 * @covers ::fetchpriority_img_tag_add_attr
 */

class Fetchpriority_Test extends WP_UnitTestCase {
	protected static $post;
	protected static $attachment_id;

	protected $current_size_filter_data   = null;
	protected $current_size_filter_result = null;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post = $factory->post->create_and_get();

		$file                = TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg';
		self::$attachment_id = $factory->attachment->create_upload_object(
			$file,
			self::$post->ID,
			array(
				'post_mime_type' => 'image/jpeg',
			)
		);
	}
	public function test_fetchpriority_img_tag_add_attr_based_on_context_and_loading_lazy() {
		$img = get_image_tag( self::$attachment_id, '', '', '', 'large' );

		$this->assertStringContainsString( 'fetchpriority="high"', fetchpriority_img_tag_add_attr( $img, 'the_content' ) );
		$this->assertStringNotContainsString( 'fetchpriority="high"', fetchpriority_img_tag_add_attr( $img, 'not_content' ) );

		$img = str_replace( '<img ', '<img loading="lazy" ', $img );
		$this->assertStringNotContainsString( 'fetchpriority="high"', fetchpriority_img_tag_add_attr( $img, 'the_content' ) );
	}
}
