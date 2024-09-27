<?php
/**
 * Web Worker Offloading integration with WooCommerce.
 *
 * @since 0.1.0
 * @package web-worker-offloading
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_filter( 'wwo_configuration', 'wwo_add_google_analytics_forwarded_events' );
wwo_mark_script_for_offloading( 'google-tag-manager' );
wwo_mark_script_for_offloading( 'woocommerce-google-analytics-integration-gtag' );
