<?php
/**
 * Tests for webp-uploads plugin settings.php.
 *
 * @package webp-uploads
 */

use WebP_Uploads\Tests\TestCase;

class Test_WebP_Uploads_Settings extends TestCase {

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

	/**
	 * @covers ::webp_uploads_generate_avif_webp_setting_callback
	 */
	public function test_webp_uploads_generate_avif_webp_setting_callback_disables_avif_and_shows_warning(): void {
		if ( ! webp_uploads_mime_type_supported( 'image/avif' ) ) {
			$this->markTestSkipped( 'Mime type image/avif is not supported.' );
		}

		$this->set_image_output_type( 'avif' );
		$this->mock_avif_transparency_support( false );
		$output    = get_echo( 'webp_uploads_generate_avif_webp_setting_callback' );
		$processor = new WP_HTML_Tag_Processor( $output );

		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'OPTION' ) ) );
		$this->assertSame( 'webp', $processor->get_attribute( 'value' ) );
		$this->assertNotNull( $processor->get_attribute( 'selected' ) );

		$this->assertTrue( $processor->next_tag( array( 'tag_name' => 'OPTION' ) ) );
		$this->assertSame( 'avif', $processor->get_attribute( 'value' ) );
		$this->assertNotNull( $processor->get_attribute( 'disabled' ) );
		$this->assertNull( $processor->get_attribute( 'selected' ) );

		$this->assertStringContainsString( 'AVIF transparency support is not available.', $output );
		$this->assertStringContainsString( 'Current ImageMagick version does not support transparent AVIF images', $output );
	}
}
