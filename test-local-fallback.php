<?php
/**
 * Test script to verify local plugin fallback functionality.
 * 
 * This script simulates the scenario described in issue #2189:
 * - External requests are disabled (WP_HTTP_BLOCK_EXTERNAL = true)
 * - Performance Lab plugins are locally installed
 * - We should see plugin cards even without external API access
 */

// Mock WordPress environment
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../../..' );
}

if ( ! defined( 'WP_HTTP_BLOCK_EXTERNAL' ) ) {
	define( 'WP_HTTP_BLOCK_EXTERNAL', true );
}

if ( ! defined( 'WP_ACCESSIBLE_HOSTS' ) ) {
	define( 'WP_ACCESSIBLE_HOSTS', '' );
}

// Mock WordPress functions that our fallback code uses
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string ) {
		return strip_tags( $string );
	}
}

if ( ! function_exists( 'wp_kses' ) ) {
	function wp_kses( $string, $allowed_html = array() ) {
		return strip_tags( $string );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'http://example.com/wp-admin/' . $path;
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'plugins_url' ) ) {
	function plugins_url( $path = '' ) {
		return 'http://example.com/wp-content/plugins/' . $path;
	}
}

// Include our fallback functionality
require_once __DIR__ . '/plugins/performance-lab/includes/admin/local-plugin-fallback.php';

// Mock plugin data (simulating what get_plugins() would return)
function mock_get_plugins() {
	return array(
		'webp-uploads/load.php' => array(
			'Name' => 'Modern Image Formats',
			'Description' => 'Converts images to modern formats like WebP and AVIF during upload and delivery to improve site performance.',
			'Version' => '2.0.0',
			'Author' => 'WordPress Performance Team',
			'AuthorURI' => 'https://make.wordpress.org/performance/',
			'PluginURI' => 'https://github.com/WordPress/performance/tree/trunk/plugins/webp-uploads',
			'RequiresWP' => '6.0',
			'RequiresPHP' => '7.4',
		),
		'optimization-detective/load.php' => array(
			'Name' => 'Optimization Detective',
			'Description' => 'Provides infrastructure for gathering optimization insights to improve site performance.',
			'Version' => '0.7.0',
			'Author' => 'WordPress Performance Team',
			'RequiresWP' => '6.5',
			'RequiresPHP' => '7.2',
		),
		'embed-optimizer/load.php' => array(
			'Name' => 'Embed Optimizer',
			'Description' => 'Optimizes the performance of embeds by lazy-loading iframes and scripts.',
			'Version' => '0.3.0',
			'Author' => 'WordPress Performance Team',
			'RequiresWP' => '6.5',
			'RequiresPHP' => '7.2',
		),
	);
}

// Mock is_plugin_active function
function mock_is_plugin_active( $plugin_file ) {
	$active_plugins = array(
		'webp-uploads/load.php',
		'optimization-detective/load.php',
		'embed-optimizer/load.php',
	);
	return in_array( $plugin_file, $active_plugins, true );
}

echo "=== Testing Local Plugin Fallback Functionality ===\n\n";

// Test 1: Check if external requests are detected as blocked
echo "Test 1: Checking external request blocking detection...\n";
$blocked = perflab_are_external_requests_blocked();
echo "External requests blocked: " . ($blocked ? 'YES' : 'NO') . "\n";
echo $blocked ? "✓ PASS - External requests correctly detected as blocked\n" : "✗ FAIL - Should detect external requests are blocked\n";
echo "\n";

// Test 2: Test local plugin data retrieval
echo "Test 2: Testing local plugin data retrieval...\n";
$plugin_slugs = array( 'webp-uploads', 'optimization-detective', 'embed-optimizer', 'nonexistent-plugin' );

// Mock get_plugins() calls
$original_get_plugins = 'get_plugins';
if ( ! function_exists( 'get_plugins' ) ) {
	function get_plugins( $plugin_folder = '' ) {
		return mock_get_plugins();
	}
}

if ( ! function_exists( 'is_plugin_active' ) ) {
	function is_plugin_active( $plugin_file ) {
		return mock_is_plugin_active( $plugin_file );
	}
}

$fallback_data = perflab_get_local_plugin_fallback_data( $plugin_slugs );

echo "Retrieved fallback data for " . count( $fallback_data ) . " plugins:\n";
foreach ( $fallback_data as $slug => $data ) {
	echo "  - {$slug}: {$data['name']} v{$data['version']} (" . ($data['is_active'] ? 'active' : 'inactive') . ")\n";
	echo "    Description: " . substr( $data['short_description'], 0, 60 ) . "...\n";
	echo "    Local fallback: " . ($data['fallback_local'] ? 'YES' : 'NO') . "\n";
}

// Test 3: Check if nonexistent plugin is correctly skipped
echo "\nTest 3: Checking nonexistent plugin handling...\n";
$has_nonexistent = isset( $fallback_data['nonexistent-plugin'] );
echo "Nonexistent plugin in results: " . ($has_nonexistent ? 'YES' : 'NO') . "\n";
echo $has_nonexistent ? "✗ FAIL - Should not include nonexistent plugins\n" : "✓ PASS - Correctly skips nonexistent plugins\n";

// Test 4: Test plugin file finding
echo "\nTest 4: Testing plugin file lookup...\n";
$mock_plugins = mock_get_plugins();
$test_cases = array(
	'webp-uploads' => 'webp-uploads/load.php',
	'optimization-detective' => 'optimization-detective/load.php', 
	'nonexistent' => false,
);

foreach ( $test_cases as $slug => $expected ) {
	$result = perflab_find_local_plugin_file( $mock_plugins, $slug );
	$passed = $result === $expected;
	echo "  {$slug}: " . ($passed ? '✓ PASS' : '✗ FAIL') . " (expected: " . ($expected ?: 'false') . ", got: " . ($result ?: 'false') . ")\n";
}

// Test 5: Test description sanitization
echo "\nTest 5: Testing description sanitization...\n";
$test_descriptions = array(
	'Simple description' => 'Simple description',
	'Description with <strong>HTML</strong> tags' => 'Description with HTML tags',
	str_repeat( 'A', 250 ) => str_repeat( 'A', 200 ) . '...',
);

foreach ( $test_descriptions as $input => $expected ) {
	$result = perflab_sanitize_plugin_description( $input );
	$passed = $result === $expected;
	echo "  " . ($passed ? '✓ PASS' : '✗ FAIL') . " - " . substr( $input, 0, 30 ) . "...\n";
	if ( ! $passed ) {
		echo "    Expected: {$expected}\n";
		echo "    Got: {$result}\n";
	}
}

echo "\n=== Test Summary ===\n";
echo "The local plugin fallback functionality has been tested.\n";
echo "If all tests show ✓ PASS, the implementation should work correctly when external requests are disabled.\n";
echo "\nTo test in a real WordPress environment:\n";
echo "1. Add define('WP_HTTP_BLOCK_EXTERNAL', true); to wp-config.php\n";
echo "2. Install Performance Lab plugin\n";
echo "3. Install some performance plugins locally (webp-uploads, optimization-detective, etc.)\n";
echo "4. Go to Settings > Performance\n";
echo "5. You should see plugin cards with '(local)' indicator even without external API access\n";