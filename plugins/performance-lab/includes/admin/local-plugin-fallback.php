<?php
/**
 * Back-compat shim: functions moved to plugins.php.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

require_once __DIR__ . '/plugins.php';
