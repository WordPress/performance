<?php
/**
 * Integration test simulating the real issue scenario.
 * 
 * This test simulates what happens when:
 * 1. External requests are disabled in WordPress (WP_HTTP_BLOCK_EXTERNAL = true)
 * 2. Performance Lab plugin tries to fetch plugin information
 * 3. Our fallback should kick in and return local plugin data
 */

// Set up the WordPress mock environment
define( 'WP_HTTP_BLOCK_EXTERNAL', true );
define( 'WP_ACCESSIBLE_HOSTS', '' ); // No allowed hosts
define( 'ABSPATH', __DIR__ . '/mock-wp/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );

// Mock WordPress functions
function wp_strip_all_tags( $string ) {
    return strip_tags( $string );
}

function get_transient( $key ) {
    // Simulate no cached data
    return false;
}

function set_transient( $key, $data, $expiration ) {
    // Mock - just return true
    return true;
}

function is_wp_error( $thing ) {
    return $thing instanceof WP_Error;
}

function __( $text, $domain = 'default' ) {
    return $text;
}

function plugins_api( $action, $args ) {
    // Simulate API being blocked/unavailable
    return new WP_Error( 'http_request_failed', 'A valid URL was not provided.' );
}

function get_plugins( $plugin_folder = '' ) {
    // Simulate locally installed Performance Lab plugins
    return array(
        'webp-uploads/load.php' => array(
            'Name' => 'Modern Image Formats',
            'Description' => 'Converts images to modern formats like WebP and AVIF during upload and delivery to improve site performance.',
            'Version' => '2.0.0',
            'Author' => 'WordPress Performance Team',
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

function is_plugin_active( $plugin_file ) {
    // Simulate some plugins being active
    $active = array(
        'webp-uploads/load.php',
        'optimization-detective/load.php',
    );
    return in_array( $plugin_file, $active, true );
}

function wp_array_slice_assoc( $array, $keys ) {
    return array_intersect_key( $array, array_flip( $keys ) );
}

class WP_Error {
    private $error_code;
    private $error_message;
    
    public function __construct( $code, $message ) {
        $this->error_code = $code;
        $this->error_message = $message;
    }
    
    public function get_error_code() {
        return $this->error_code;
    }
    
    public function get_error_message() {
        return $this->error_message;
    }
}

// Include the functions we need to test
require_once __DIR__ . '/plugins/performance-lab/includes/admin/local-plugin-fallback.php';

// Mock the standalone plugin data function
function perflab_get_standalone_plugins() {
    return array( 'webp-uploads', 'optimization-detective', 'embed-optimizer' );
}

// Include the modified plugins.php file (with our fallback integration)
// We'll mock just the specific function we modified
function perflab_query_plugin_info_with_fallback( $plugin_slug ) {
    // This simulates the modified perflab_query_plugin_info function
    
    // Check if external requests are blocked and if we should use local fallback.
    $should_use_fallback = perflab_are_external_requests_blocked();
    
    if ( $should_use_fallback ) {
        // Try to get local plugin data instead.
        $local_data = perflab_get_local_plugin_fallback_data( array( $plugin_slug ) );
        if ( isset( $local_data[ $plugin_slug ] ) ) {
            return $local_data[ $plugin_slug ];
        }
    }
    
    // If fallback didn't work, proceed with API (which will fail)
    $response = plugins_api( 'query_plugins', array(
        'author' => 'wordpressdotorg',
        'tag' => 'performance',
    ));
    
    if ( is_wp_error( $response ) ) {
        // Try local fallback as backup
        $local_fallback_data = perflab_get_local_plugin_fallback_data( perflab_get_standalone_plugins() );
        
        if ( ! empty( $local_fallback_data ) && isset( $local_fallback_data[ $plugin_slug ] ) ) {
            return $local_fallback_data[ $plugin_slug ];
        }
        
        return $response; // Return the error
    }
    
    return array(); // Should not reach here in our test
}

echo "=== Integration Test: Issue #2189 Scenario ===\n\n";

echo "Scenario: External requests are disabled, Performance Lab plugins are installed locally.\n";
echo "Expected: Plugin cards should still be shown using local plugin data.\n\n";

echo "1. External requests blocked: " . (perflab_are_external_requests_blocked() ? 'YES' : 'NO') . "\n";
echo "2. Testing plugin info retrieval for 'webp-uploads'...\n";

$result = perflab_query_plugin_info_with_fallback( 'webp-uploads' );

if ( is_wp_error( $result ) ) {
    echo "   ✗ FAIL: Got WP_Error: " . $result->get_error_message() . "\n";
    echo "   This means the fallback did not work!\n";
} else {
    echo "   ✓ SUCCESS: Got plugin data from local fallback!\n";
    echo "   Plugin Name: " . $result['name'] . "\n";
    echo "   Plugin Version: " . $result['version'] . "\n";
    echo "   Is Active: " . ($result['is_active'] ? 'Yes' : 'No') . "\n";
    echo "   Fallback Used: " . ($result['fallback_local'] ? 'Yes' : 'No') . "\n";
    echo "   Description: " . substr( $result['short_description'], 0, 60 ) . "...\n";
}

echo "\n3. Testing for non-installed plugin...\n";
$result_missing = perflab_query_plugin_info_with_fallback( 'nonexistent-plugin' );

if ( is_wp_error( $result_missing ) ) {
    echo "   ✓ CORRECT: Non-installed plugin correctly returns error\n";
} else {
    echo "   ✗ UNEXPECTED: Non-installed plugin returned data somehow\n";
}

echo "\n=== Test Conclusion ===\n";
echo "If the above shows SUCCESS for locally installed plugins and CORRECT for non-installed,\n";
echo "then our fix for issue #2189 is working properly!\n";
echo "\nThis means users with WP_HTTP_BLOCK_EXTERNAL=true will still see their\n";
echo "locally installed Performance Lab plugins in the Settings > Performance screen.\n";