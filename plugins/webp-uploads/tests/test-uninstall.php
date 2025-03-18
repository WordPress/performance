<?php
/**
 * Tests for webp-uploads plugin uninstall.php.
 *
 * @package webp-uploads
 */

class Test_WebP_Uploads_Uninstall extends WP_UnitTestCase {

	/**
	 * Test uninstall on a single site.
	 */
	public function test_uninstall_single_site(): void {
		// Set options to ensure they exist before uninstall.
		update_option( 'perflab_generate_webp_and_jpeg', true );
		update_option( 'perflab_generate_all_fallback_sizes', true );

		include_once __DIR__ . '/../uninstall.php';

		// Assert options are deleted.
		$this->assertFalse( get_option( 'perflab_generate_webp_and_jpeg' ) );
		$this->assertFalse( get_option( 'perflab_generate_all_fallback_sizes' ) );
	}

	/**
	 * Test uninstall on a multisite.
	 */
	public function test_uninstall_multisite(): void {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'This test is for multisite only.' );
		}

		// Create multiple sites.
		$site_ids = self::factory()->blog->create_many( 3 );

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			// Set options to ensure they exist before uninstall.
			update_option( 'perflab_generate_webp_and_jpeg', true );
			update_option( 'perflab_generate_all_fallback_sizes', true );
			restore_current_blog();
		}

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}
		include_once __DIR__ . '/../uninstall.php';

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			// Assert options are deleted.
			$this->assertFalse( get_option( 'perflab_generate_webp_and_jpeg' ) );
			$this->assertFalse( get_option( 'perflab_generate_all_fallback_sizes' ) );
			restore_current_blog();
		}
	}
}
