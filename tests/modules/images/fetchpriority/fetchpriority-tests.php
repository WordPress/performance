<?php
/**
 * Tests the adding of fetchpriority to img tags in the content.
 *
 * @covers ::fetchpriority_img_tag_add_attr
 */
class Fetchpriority_Test extends WP_UnitTestCase {
	protected static $post;
	protected static $attachment_id;
	protected static $attachment_id_2;

	protected $current_size_filter_data   = null;
	protected $current_size_filter_result = null;

	public static function wpSetUpBeforeClass( WP_UnitTest_Factory $factory ) {
		self::$post            = $factory->post->create_and_get();
		$file                  = DIR_TESTDATA . '/images/canola.jpg';
		self::$attachment_id   = $factory->attachment->create_upload_object(
			$file,
			self::$post->ID,
			array(
				'post_mime_type' => 'image/jpeg',
			)
		);
		self::$attachment_id_2 = $factory->attachment->create_upload_object(
			$file,
			self::$post->ID,
			array(
				'post_mime_type' => 'image/jpeg',
			)
		);
	}

	public static function tear_down_after_class() {
		wp_delete_attachment( self::$attachment_id, true );
		wp_delete_attachment( self::$attachment_id_2, true );
		parent::tear_down_after_class();
	}

	public function set_up() {
		parent::set_up();

		if ( ! perflab_can_load_module( 'images/fetchpriority' ) ) {
			$this->markTestSkipped( 'Fetchpriority module tests irrelevant since available in WordPress core' );
		}
	}

	public function test_fetchpriority_img_tag_add_attr_based_on_context_and_loading_lazy() {
		$img = get_image_tag( self::$attachment_id, '', '', '', 'large' );

		$this->assertStringContainsString( 'fetchpriority="high"', fetchpriority_img_tag_add_attr( $img, 'the_content' ) );
		$this->assertStringNotContainsString( 'fetchpriority="high"', fetchpriority_img_tag_add_attr( $img, 'not_content' ) );

		$img = str_replace( '<img ', '<img loading="lazy" ', $img );
		$this->assertStringNotContainsString( 'fetchpriority="high"', fetchpriority_img_tag_add_attr( $img, 'the_content' ) );
	}

	public function test_fetchpriority_img_tag_add_in_wp_filter_content_tags() {
		global $wp_query;
		global $wp_the_query;
		$img   = get_image_tag( self::$attachment_id, '', '', '', 'large' );
		$img_2 = get_image_tag( self::$attachment_id_2, '', '', '', 'large' );

		$img = '<!-- wp:image {"id":' . self::$attachment_id . ',"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large">' . $img . '</figure>
<!-- /wp:image -->
<!-- wp:paragraph -->
<p>This is an example page.</p>
<!-- /wp:paragraph -->
<!-- wp:image {"id":' . self::$attachment_id_2 . ',"sizeSlug":"large","linkDestination":"none"} -->
<figure class="wp-block-image size-large">' . $img_2 . '</figure>
<!-- /wp:image -->
';
		// Ensure image filtering occurs 'in_the_loop', is_main_query.
		$wp_the_query          = $wp_query;
		$wp_query->in_the_loop = true;
		$content               = wp_filter_content_tags( $img, 'the_content' );
		$this->assertStringContainsString( 'fetchpriority="high"', $content );
		$this->assertStringContainsString( 'loading="lazy"', $content );
		$this->assertTrue( strpos( $content, 'fetchpriority="high"' ) < strpos( $content, 'loading="lazy"' ) );

		$this->assertEquals( 1, substr_count( $content, 'fetchpriority' ) );

		// Disable lazy loading and verify fetchpriority isn't added.
		add_filter( 'wp_lazy_loading_enabled', '__return_false' );
		$content = wp_filter_content_tags( $img, 'the_content' );
		$this->assertStringNotContainsString( 'fetchpriority="high"', $content );

	}
}
