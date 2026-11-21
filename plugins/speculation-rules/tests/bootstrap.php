<?php
/**
 * Test Bootstrap for Speculative Loading.
 *
 * @package speculation-rules
 */

// Load conditionally loaded files unconditionally so that they can be tested.
require_once TESTS_PLUGIN_DIR . '/class-plsr-url-pattern-prefixer.php';
require_once TESTS_PLUGIN_DIR . '/plugin-api.php';
require_once TESTS_PLUGIN_DIR . '/wp-core-api.php';
