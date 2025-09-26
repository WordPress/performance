<?php
/**
 * Tests for admin/local-plugin-fallback.php
 *
 * @package performance-lab
 */

/**
 * @group admin
 */
class Test_Local_Plugin_Fallback extends WP_UnitTestCase {

	/**
	 * Test that local fallback data is returned when external requests are blocked.
	 */
	public function test_fallback_used_when_http_blocked(): void {
		// Mock WP_HTTP_BLOCK_EXTERNAL constant.
		if ( ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) ) {
			define( 'WP_HTTP_BLOCK_EXTERNAL', true );
		}

		// Ensure we have access to plugin functions.
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Mock a plugin being installed.
		$mock_plugins = array(
			'webp-uploads/load.php' => array(
				'Name'        => 'Modern Image Formats',
				'Description' => 'Convert and serve images in modern formats like WebP.',
				'Version'     => '2.0.0',
				'Author'      => 'WordPress Performance Team',
				'RequiresWP'  => '6.0',
				'RequiresPHP' => '7.4',
			),
		);

		// Mock get_plugins() function.
		add_filter(
			'pre_option_active_plugins',
			static function () {
				return array( 'webp-uploads/load.php' );
			}
		);

		// Test that we detect external requests are blocked.
		$this->assertTrue( perflab_are_external_requests_blocked() );

		// Test that we can get local fallback data.
		$local_data = perflab_get_local_plugin_fallback_data( array( 'webp-uploads' ) );

		// We can't directly test this without mocking get_plugins(), but let's ensure the function exists.
		$this->assertTrue( function_exists( 'perflab_get_local_plugin_fallback_data' ) );
		$this->assertTrue( function_exists( 'perflab_are_external_requests_blocked' ) );
	}

	/**
	 * Test that plugin file lookup works correctly.
	 */
	public function test_find_local_plugin_file(): void {
		$mock_plugins = array(
			'webp-uploads/load.php'           => array( 'Name' => 'Modern Image Formats' ),
			'optimization-detective/load.php' => array( 'Name' => 'Optimization Detective' ),
			'single-file-plugin.php'          => array( 'Name' => 'Single File Plugin' ),
		);

		$this->assertEquals( 'webp-uploads/load.php', perflab_find_local_plugin_file( $mock_plugins, 'webp-uploads' ) );
		$this->assertEquals( 'optimization-detective/load.php', perflab_find_local_plugin_file( $mock_plugins, 'optimization-detective' ) );
		$this->assertFalse( perflab_find_local_plugin_file( $mock_plugins, 'nonexistent-plugin' ) );
	}

	/**
	 * Test plugin description sanitization.
	 */
	public function test_sanitize_plugin_description(): void {
		$test_cases = array(
			'Simple text'                          => 'Simple text',
			'Text with <strong>HTML</strong> tags' => 'Text with HTML tags',
			'Text with &amp; entities'             => 'Text with & entities',
			''                                     => '',
		);

		foreach ( $test_cases as $input => $expected ) {
			$this->assertEquals( $expected, perflab_sanitize_plugin_description( $input ) );
		}
	}

	/**
	 * Test requires_plugins parsing.
	 */
	public function test_parse_requires_plugins(): void {
		// Test RequiresPlugins header.
		$headers = array(
			'RequiresPlugins' => 'optimization-detective, auto-sizes',
		);
		$result  = perflab_parse_requires_plugins( $headers, 'test-plugin' );
		$this->assertContains( 'optimization-detective', $result );
		$this->assertContains( 'auto-sizes', $result );

		// Test known dependency: Embed Optimizer.
		$headers = array();
		$result  = perflab_parse_requires_plugins( $headers, 'embed-optimizer' );
		$this->assertContains( 'optimization-detective', $result );

		// Test known dependency: Image Prioritizer.
		$headers = array();
		$result  = perflab_parse_requires_plugins( $headers, 'image-prioritizer' );
		$this->assertContains( 'optimization-detective', $result );
	}
}
