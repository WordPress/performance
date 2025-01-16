<?php
/**
 * Hook callbacks used for Image Prioritizer.
 *
 * @package image-prioritizer
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'od_init', 'image_prioritizer_init' );
