<?php
/**
 * Module Name: Fetchpriority
 * Description: Adds a fetchpriority hint for the primary content image on the page to load faster.
 * Experimental: No
 *
 * @since 1.8.0
 * @package performance-lab
 */

// Define the constant.
if ( defined( 'FETCHPRIORITY_VERSION' ) ) {
	return;
}

define( 'FETCHPRIORITY_VERSION', 'Performance Lab ' . PERFLAB_VERSION );

// Do not load the code if it is already loaded through another means.
if ( function_exists( 'fetchpriority_img_tag_add_attr' ) ) {
	return;
}

require_once __DIR__ . '/hooks.php';
