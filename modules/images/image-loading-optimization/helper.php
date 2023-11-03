<?php
/**
 * Helper functions used for Image Loading Optimization.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Gets the TTL for the metrics storage lock.
 *
 * @return int TTL.
 */
function image_loading_optimization_get_metrics_storage_lock_ttl() {

	/**
	 * Filters how long a given IP is locked from submitting another metrics-storage REST API request.
	 *
	 * Filtering the TTL to zero will disable any metrics storage locking. This is useful during development.
	 *
	 * @param int $ttl TTL.
	 */
	return (int) apply_filters( 'perflab_image_loading_optimization_metrics_storage_lock_ttl', MINUTE_IN_SECONDS );
}

/**
 * Gets transient key for locking metrics storage (for the current IP).
 *
 * @todo Should the URL be included in the key? Or should a user only be allowed to store one metric?
 * @return string Transient key.
 */
function image_loading_optimization_get_metrics_storage_lock_transient_key() {
	$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
	return 'page_metrics_storage_lock_' . wp_hash( $ip_address );
}

/**
 * Sets metrics storage lock (for the current IP).
 */
function image_loading_optimization_set_metrics_storage_lock() {
	$ttl = image_loading_optimization_get_metrics_storage_lock_ttl();
	$key = image_loading_optimization_get_metrics_storage_lock_transient_key();
	if ( 0 === $ttl ) {
		delete_transient( $key );
	} else {
		set_transient( $key, time(), $ttl );
	}
}

/**
 * Checks whether metrics storage is locked (for the current IP).
 *
 * @return bool Whether locked.
 */
function image_loading_optimization_is_metrics_storage_locked() {
	$ttl = image_loading_optimization_get_metrics_storage_lock_ttl();
	if ( 0 === $ttl ) {
		return false;
	}
	$locked_time = (int) get_transient( image_loading_optimization_get_metrics_storage_lock_transient_key() );
	if ( 0 === $locked_time ) {
		return false;
	}
	return time() - $locked_time < $ttl;
}
