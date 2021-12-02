<?php
/**
 * Module Name: WebP default.
 * Description: Use WebP as the default sub sized image format.
 *              This module changes the default format WordPress uses for
 *              generating sub sized images from JPEG to WebP. Requires that the
 *              server supports WebP and only acts on JPEG image uploads.
 * Focus: images
 * Experimental: No
 *
 * @package performance-lab
 */
require_once( plugin_dir_path( __FILE__ ) . 'class-webp-default.php' );

new WebP_Default();
