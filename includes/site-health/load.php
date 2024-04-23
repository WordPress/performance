<?php
/**
 * Site Health checks loader.
 *
 * @package performance-lab
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Audit Autoloaded Options site health check.
require_once __DIR__ . '/audit-autoloaded-options/helper.php';
require_once __DIR__ . '/audit-autoloaded-options/hooks.php';

// Audit Enqueued Assets site health check.
require_once __DIR__ . '/audit-enqueued-assets/helper.php';
require_once __DIR__ . '/audit-enqueued-assets/hooks.php';

// WebP Support site health check.
require_once __DIR__ . '/webp-support/helper.php';
require_once __DIR__ . '/webp-support/hooks.php';
