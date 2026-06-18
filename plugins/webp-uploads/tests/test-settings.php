<?php
/**
 * Tests for webp-uploads plugin settings.php.
 *
 * @package webp-uploads
 */

use WebP_Uploads\Tests\TestCase;

class Test_WebP_Uploads_Settings extends TestCase {

	public function tear_down(): void {
		remove_all_filters( 'webp_uploads_imagick_avif_transparency_supported' );
		delete_option( 'perflab_modern_image_format' );

		parent::tear_down();
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
	public function test_webp_uploads_generate_avif_webp_setting_callback_shows_avif_transparency_warning(): void {
		if ( ! webp_uploads_mime_type_supported( 'image/avif' ) ) {
			$this->markTestSkipped( 'Mime type image/avif is not supported.' );
		}

		$this->set_image_output_type( 'avif' );

		$this->mock_avif_transparency_support( false );

		ob_start();
		webp_uploads_generate_avif_webp_setting_callback();
		$output = ob_get_clean();

		$this->assertIsString( $output );
		$this->assertStringContainsString( 'AVIF transparency support is not available.', $output );
		$this->assertStringContainsString( 'Transparent images may lose transparency when generated as AVIF.', $output );
	}

	/**
	 * Mocks AVIF transparency support to force a specific scenario.
	 *
	 * @param bool $supported Whether to mock AVIF transparency as supported.
	 */
	private function mock_avif_transparency_support( bool $supported ): void {
		add_filter(
			'webp_uploads_imagick_avif_transparency_supported',
			static function () use ( $supported ) {
				return $supported;
			},
			1
		);
	}
}
