<?php
/**
 * Tests for optimization-detective plugin uninstall.php.
 *
 * @runInSeparateProcess
 * @package optimization-detective
 */

class OD_Uninstall_Tests extends WP_UnitTestCase {

	/**
	 * Runs the routine before setting up all tests.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		// Mock uninstall const.
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'Yes' );
		}
	}

	/**
	 * Load uninstall.php.
	 */
	private function require_uninstall(): void {
		require __DIR__ . '/../../../plugins/optimization-detective/uninstall.php';
	}

	/**
	 * Make sure post deletion is happening.
	 */
	public function test_post_deletion(): void {

		$post_id             = self::factory()->post->create();
		$url_metrics_post_id = self::factory()->post->create( array( 'post_type' => OD_URL_Metrics_Post_Type::SLUG ) );

		$this->require_uninstall();
		wp_cache_flush();

		$this->assertInstanceOf( WP_Post::class, get_post( $post_id ) );
		$this->assertNull( get_post( $url_metrics_post_id ) );
	}

	/**
	 * Test scheduled event removal.
	 */
	public function test_event_removal(): void {
		$user = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );
		OD_URL_Metrics_Post_Type::schedule_garbage_collection();
		$scheduled_event = wp_get_scheduled_event( OD_URL_Metrics_Post_Type::GC_CRON_EVENT_NAME );
		$this->assertIsObject( $scheduled_event );

		$this->require_uninstall();

		$this->assertFalse( wp_get_scheduled_event( OD_URL_Metrics_Post_Type::GC_CRON_EVENT_NAME ) );
	}
}
