<?php
/**
 * Metrics storage locking.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Gets the TTL for the page metric storage lock.
 *
 * @return int TTL.
 */
function ilo_get_page_metric_storage_lock_ttl() {

	/**
	 * Filters how long a given IP is locked from submitting another metric-storage REST API request.
	 *
	 * Filtering the TTL to zero will disable any metric storage locking. This is useful during development.
	 *
	 * @param int $ttl TTL.
	 */
	return (int) apply_filters( 'ilo_metrics_storage_lock_ttl', MINUTE_IN_SECONDS );
}

/**
 * Gets transient key for locking page metric storage (for the current IP).
 *
 * @todo Should the URL be included in the key? Or should a user only be allowed to store one metric?
 * @return string Transient key.
 */
function ilo_get_page_metric_storage_lock_transient_key() {
	$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
	return 'page_metrics_storage_lock_' . wp_hash( $ip_address );
}

/**
 * Sets page metric storage lock (for the current IP).
 */
function ilo_set_page_metric_storage_lock() {
	$ttl = ilo_get_page_metric_storage_lock_ttl();
	$key = ilo_get_page_metric_storage_lock_transient_key();
	if ( 0 === $ttl ) {
		delete_transient( $key );
	} else {
		set_transient( $key, time(), $ttl );
	}
}

/**
 * Checks whether page metric storage is locked (for the current IP).
 *
 * @return bool Whether locked.
 */
function ilo_is_page_metric_storage_locked() {
	$ttl = ilo_get_page_metric_storage_lock_ttl();
	if ( 0 === $ttl ) {
		return false;
	}
	$locked_time = (int) get_transient( ilo_get_page_metric_storage_lock_transient_key() );
	if ( 0 === $locked_time ) {
		return false;
	}
	return time() - $locked_time < $ttl;
}
