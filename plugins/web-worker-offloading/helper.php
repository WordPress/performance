<?php
/**
 * Helpers for Web Worker Offloading.
 *
 * @since 0.1.0
 * @package web-worker-offloading
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Gets configuration for Web Worker Offloading.
 *
 * @since 0.1.0
 * @link https://partytown.builder.io/configuration
 *
 * @return array{ debug?: bool, forward?: non-empty-string[], lib: non-empty-string, loadScriptsOnMainThread?: non-empty-string[], nonce?: non-empty-string } Configuration for Partytown.
 */
function wwo_get_configuration(): array {
	$config = array(
		'lib'     => wp_parse_url( plugin_dir_url( __FILE__ ), PHP_URL_PATH ) . 'build/',
		'forward' => array(),
	);

	/**
	 * Add configuration for Web Worker Offloading.
	 *
	 * @since 0.1.0
	 * @link https://partytown.builder.io/configuration
	 *
	 * @param array{ debug?: bool, forward?: non-empty-string[], lib: non-empty-string, loadScriptsOnMainThread?: non-empty-string[], nonce?: non-empty-string } $config Configuration for Partytown.
	 */
	return apply_filters( 'wwo_configuration', $config );
}
