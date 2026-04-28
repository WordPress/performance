<?php
/**
 * Site Health checks loader.
 *
 * @package webp-uploads
 * @since n.e.x.t
 */

declare( strict_types = 1 );

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Imagick AVIF transparency support site health check.
require_once __DIR__ . '/imagick-avif-transparency-support/helper.php';
require_once __DIR__ . '/imagick-avif-transparency-support/hooks.php';
// @codeCoverageIgnoreEnd
