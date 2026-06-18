<?php
/**
 * Tests for webp-uploads plugin settings.php.
 *
 * @package webp-uploads
 */

class Test_WebP_Uploads_Settings extends WP_UnitTestCase {

	/**
	 * @covers ::webp_uploads_add_media_settings_fields
	 */
	public function test_webp_uploads_add_media_settings_fields_section_description(): void {
		global $wp_settings_sections;
		webp_uploads_add_media_settings_fields();

		$callback = $wp_settings_sections['media']['perflab_modern_image_format_settings']['callback'];
		$this->assertIsCallable( $callback );

		$processor = new WP_HTML_Tag_Processor( get_echo( $callback ) );
		$this->assertTrue( $processor->next_tag( 'A' ) );
		$this->assertSame( 'https://wordpress.org/plugins/webp-uploads/#faq', $processor->get_attribute( 'href' ) );
	}

	/**
	 * @covers ::webp_uploads_add_settings_action_link
	 */
	public function test_webp_uploads_add_settings_action_link(): void {
		$this->assertSame( 10, has_filter( 'plugin_action_links_' . WEBP_UPLOADS_MAIN_FILE, 'webp_uploads_add_settings_action_link' ) );
		$this->assertFalse( webp_uploads_add_settings_action_link( false ) );

		$default_action_links = array(
			'deactivate' => '<a href="plugins.php?action=deactivate&amp;plugin=webp-uploads%2Fload.php&amp;plugin_status=all&amp;paged=1&amp;s&amp;_wpnonce=48f74bdd74" id="deactivate-webp-uploads" aria-label="Deactivate Modern Image Formats">Deactivate</a>',
		);

		$this->assertSame(
			array_merge(
				array(
					'settings' => '<a href="' . esc_url( admin_url( 'options-media.php#modern-image-formats' ) ) . '">Settings</a>',
				),
				$default_action_links
			),
			webp_uploads_add_settings_action_link( $default_action_links )
		);
	}
}
