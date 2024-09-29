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
wwo_mark_scripts_for_offloading(
	array(
		'google-tag-manager',
		'woocommerce-google-analytics-integration-gtag',
	)
);
