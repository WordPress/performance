<?php
/**
 * Storage for image loading optimization.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once __DIR__ . '/storage/lock.php';
require_once __DIR__ . '/storage/post-type.php';
require_once __DIR__ . '/storage/data.php';
require_once __DIR__ . '/storage/rest-api.php';
