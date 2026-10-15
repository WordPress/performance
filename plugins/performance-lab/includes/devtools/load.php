<?php
/**
 * Chrome DevTools third-party tools integration.
 *
 * Exposes read-only WordPress performance and debugging state to AI agents via the experimental
 * Chrome DevTools third-party tools API.
 *
 * @package performance-lab
 * @since n.e.x.t
 *
 * @link https://developer.chrome.com/docs/devtools/agents/use-cases/third-party-tools
 */

declare( strict_types = 1 );

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/hooks.php';
