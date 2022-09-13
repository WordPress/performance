<?php

class Fetchpriority_Tests extends WP_UnitTestCase {

	public function test_wp_filter_content_tags_filter_adds_fetchpriority_fist_image_only() {
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );
		$img           = get_image_tag( $attachment_id, '', '', '', 'large' );

		$this->assertStringContainsString( 'fetchpriority="high"', fetchpriority_img_tag_add( $img, 'the_content' ) );
		$this->assertStringNotContainsString( 'fetchpriority="high"', fetchpriority_img_tag_add( $img, 'not_content' ) );

		$img = str_replace( '<img ', '<img loading="lazy" ', $img );
		$this->assertStringNotContainsString( 'fetchpriority="high"', fetchpriority_img_tag_add( $img, 'the_content' ) );
	}
}
