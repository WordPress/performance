<?php
/**
 * Tests for the admin asset enqueueing in hooks.php.
 *
 * @package ai-performance-advisor
 */

declare( strict_types = 1 );

class AIPA_Test_Hooks extends WP_UnitTestCase {

	public function tear_down(): void {
		wp_dequeue_style( 'aipa-analyzer' );
		wp_dequeue_script( 'aipa-analyzer' );
		wp_deregister_style( 'aipa-analyzer' );
		wp_deregister_script( 'aipa-analyzer' );
		parent::tear_down();
	}

	/**
	 * @covers ::aipa_enqueue_admin_assets
	 */
	public function test_assets_not_enqueued_on_other_screens(): void {
		aipa_enqueue_admin_assets( 'index.php' );
		$this->assertFalse( wp_style_is( 'aipa-analyzer', 'enqueued' ) );
		$this->assertFalse( wp_script_is( 'aipa-analyzer', 'enqueued' ) );
	}

	/**
	 * @covers ::aipa_enqueue_admin_assets
	 */
	public function test_assets_enqueued_on_site_health_screen(): void {
		aipa_enqueue_admin_assets( 'site-health.php' );

		$this->assertTrue( wp_style_is( 'aipa-analyzer', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'aipa-analyzer', 'enqueued' ) );

		// The script declares its dependencies on apiFetch and i18n.
		$script = wp_scripts()->registered['aipa-analyzer'];
		$this->assertContains( 'wp-api-fetch', $script->deps );
		$this->assertContains( 'wp-i18n', $script->deps );
	}
}
