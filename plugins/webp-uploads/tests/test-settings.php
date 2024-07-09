<?php
/**
 * Tests for webp-uploads plugin settings.php.
 *
 * @package webp-uploads
 */

class Test_WebP_Uploads_Settings extends WP_UnitTestCase {

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
