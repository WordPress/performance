<?php

/**
 * Tests the adding of fetchpriority to img tags in the content.
 *
 * @Covers ::wp_filter_content_tags
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

	public function test_fetchpriority_img_tag_add_in_wp_filter_content_tags() {
		$img = get_image_tag( self::$attachment_id, '', '', '', 'large' );

		$img     = '<!-- wp:image {"id":' . self::$attachment_id . ',"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large">' . $img . '</figure>
<!-- /wp:image -->
<!-- wp:paragraph -->
<p>This is an example page. It\'s different from a blog post because it will stay in one place and will show up in your site navigation (in most themes). Most people start with an About page that introduces them to potential site visitors. It might say something like this:</p>
<!-- /wp:paragraph -->
<!-- wp:image {"id":' . self::$attachment_id . ',"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large">' . $img . '</figure>
<!-- /wp:image -->
';
		$content = wp_filter_content_tags( $img, 'the_content' );
		$this->assertStringContainsString( 'fetchpriority="high"', $content );

		$spilt = explode( 'fetchpriority', $content );
		$this->assertEquals( 2, count( $spilt ) );
	}
	public function test_get_the_post_thumbnail() {
		set_post_thumbnail( self::$post, self::$attachment_id );
		$this->assertStringContainsString( 'fetchpriority="high"', get_the_post_thumbnail( self::$post ) );
	}
}
