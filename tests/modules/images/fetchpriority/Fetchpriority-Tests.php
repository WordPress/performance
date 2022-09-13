<?php

class Fetchpriority_Test extends WP_UnitTestCase {

	public function test_fetchpriority_img_tag_add_attr_based_on_context_and_loading_lazy() {
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );
		$img           = get_image_tag( $attachment_id, '', '', '', 'large' );

		$this->assertStringContainsString( 'fetchpriority="high"', fetchpriority_img_tag_add_attr( $img, 'the_content' ) );
		$this->assertStringNotContainsString( 'fetchpriority="high"', fetchpriority_img_tag_add_attr( $img, 'not_content' ) );

		$img = str_replace( '<img ', '<img loading="lazy" ', $img );
		$this->assertStringNotContainsString( 'fetchpriority="high"', fetchpriority_img_tag_add_attr( $img, 'the_content' ) );
	}
}
