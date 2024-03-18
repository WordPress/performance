<?php
/**
 * Tests for optimization-detective module uninstall.php.
 *
 * @runInSeparateProcess
 * @package optimization-detective
 */

class OD_Uninstall_Tests extends WP_UnitTestCase {

	/**
	 * Make sure post deletion is happening.
	 */
	public function test_post_deletion() {
		// Mock uninstall const.
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'Yes' );
		}

		$post_id             = self::factory()->post->create();
		$url_metrics_post_id = self::factory()->post->create( array( 'post_type' => OD_URL_Metrics_Post_Type::SLUG ) );

		require __DIR__ . '/../../../plugins/optimization-detective/uninstall.php';
		wp_cache_flush();

		$this->assertInstanceOf( WP_Post::class, get_post( $post_id ) );
		$this->assertNull( get_post( $url_metrics_post_id ) );
	}
}
