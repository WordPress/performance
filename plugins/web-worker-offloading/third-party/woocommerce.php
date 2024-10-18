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

	$configuration['mainWindowAccessors'][] = 'wp';   // Because woocommerce-google-analytics-integration needs to access wp.i18n.
	$configuration['mainWindowAccessors'][] = 'ga4w'; // Because woocommerce-google-analytics-integration needs to access window.ga4w.
	$configuration['globalFns'][]           = 'gtag'; // Because gtag() is defined in one script and called in another.
	$configuration['forward'][]             = 'dataLayer.push'; // Because the Partytown integration has this in its example config.
	return $configuration;
}
add_filter( 'plwwo_configuration', 'plwwo_woocommerce_configure' );

plwwo_mark_scripts_for_offloading(
	array(
		'google-tag-manager',
		'woocommerce-google-analytics-integration',
		'woocommerce-google-analytics-integration-gtag',
	)
);
