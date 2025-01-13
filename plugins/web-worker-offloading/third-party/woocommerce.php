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

/**
 * Configures WWO for WooCommerce and Google Analytics.
 *
 * @since 0.1.0
 * @access private
 * @link https://partytown.builder.io/google-tag-manager#forward-events
 *
 * @param array<string, mixed>|mixed $configuration Configuration.
 * @return array<string, mixed> Configuration.
 */
function plwwo_woocommerce_configure( $configuration ): array {
	$configuration = (array) $configuration;

	$configuration['globalFns'][] = 'gtag'; // Allow calling from other Partytown scripts.

	// Expose on the main tread. See <https://partytown.builder.io/forwarding-event>.
	$configuration['forward'][] = 'dataLayer.push';
	$configuration['forward'][] = 'gtag';

	return $configuration;
}
add_filter( 'plwwo_configuration', 'plwwo_woocommerce_configure' );

plwwo_mark_scripts_for_offloading(
	// Note: 'woocommerce-google-analytics-integration' is intentionally not included because for some reason events like add_to_cart don't get tracked.
	array(
		'google-tag-manager',
		'woocommerce-google-analytics-integration-gtag',
	)
);
