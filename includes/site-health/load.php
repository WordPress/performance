<?php
/**
 * Site Health checks loader.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! has_filter( 'user_has_cap', 'wp_maybe_grant_site_health_caps' ) ) {
	return;
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
