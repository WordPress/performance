<?php

namespace modules\images\fetchpriority;

class Fetchpriority_Tests extends WP_UnitTestCase {

	public function test_wp_filter_content_tags_filter_adds_fetchpriority_fist_image_only() {
		$attachment_id = $this->factory->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/leafs.jpg' );
		$img           = get_image_tag( $attachment_id, '', '', '', 'large' );
		$content       = "$img\n$img";

		$this->assertStringContainsString( 'fetchpriority ', wp_filter_content_tags( $content ) );
	}
}
