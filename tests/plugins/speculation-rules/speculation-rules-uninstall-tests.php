<?php
/**
 * Tests for speculation-rules plugin uninstall.php.
 *
 * @runInSeparateProcess
 * @package speculation-rules
 */

class Speculation_Rules_Uninstall_Tests extends WP_UnitTestCase {

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
		require __DIR__ . '/../../../plugins/speculation-rules/uninstall.php';
	}

	/**
	 * Test option deletion.
	 *
	 * @covers ::plsr_delete_plugin_option
	 */
	public function test_delete_plugin_option(): void {
		unregister_setting( 'reading', 'plsr_speculation_rules' );
		$test_blogname = 'Hello World';
		update_option( 'blogname', $test_blogname );
		update_option( 'plsr_speculation_rules', plsr_get_setting_default() );

		$this->assertEquals( $test_blogname, $test_blogname );
		$this->assertEquals( plsr_get_setting_default(), get_option( 'plsr_speculation_rules' ) );

		$this->require_uninstall();

		$this->assertEquals( $test_blogname, get_option( 'blogname' ) );
		$this->assertFalse( get_option( 'plsr_speculation_rules' ) );
	}
}
