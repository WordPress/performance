<?php
/**
 * Metrics storage lock.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Gets the TTL (in seconds) for the page metric storage lock.
 *
 * @return int TTL in seconds, greater than or equal to zero.
 */
function ilo_get_page_metric_storage_lock_ttl(): int {

	/**
	 * Filters how long a given IP is locked from submitting another metric-storage REST API request.
	 *
	 * Filtering the TTL to zero will disable any metric storage locking. This is useful, for example, to disable
	 * locking when a user is logged-in with code like the following:
	 *
	 *     add_filter( 'ilo_metrics_storage_lock_ttl', static function ( $ttl ) {
	 *         return is_user_logged_in() ? 0 : $ttl;
	 *     } );
	 *
	 * @param int $ttl TTL.
	 */
	$ttl = (int) apply_filters( 'ilo_metrics_storage_lock_ttl', MINUTE_IN_SECONDS );
	return max( 0, $ttl );
}

/**
 * Gets transient key for locking page metric storage (for the current IP).
 *
 * @todo Should the URL be included in the key? Or should a user only be allowed to store one metric?
 * @return string Transient key.
 */
function ilo_get_page_metric_storage_lock_transient_key(): string {
	$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
	return 'page_metrics_storage_lock_' . wp_hash( $ip_address );
}

/**
 * Sets page metric storage lock (for the current IP).
 *
 * If the storage lock TTL is greater than zero, then a transient is set with the current timestamp and expiring at TTL
 * seconds. Otherwise, if the current TTL is zero, then any transient is deleted.
 */
function ilo_set_page_metric_storage_lock() /*: void (in PHP 7.1) */ {
	$ttl = ilo_get_page_metric_storage_lock_ttl();
	$key = ilo_get_page_metric_storage_lock_transient_key();
	if ( 0 === $ttl ) {
		delete_transient( $key );
	} else {
		set_transient( $key, microtime( true ), $ttl );
	}
}

/**
 * Checks whether page metric storage is locked (for the current IP).
 *
 * @return bool Whether locked.
 */
function ilo_is_page_metric_storage_locked(): bool {
	$ttl = ilo_get_page_metric_storage_lock_ttl();
	if ( 0 === $ttl ) {
		return false;
	}
	$locked_time = get_transient( ilo_get_page_metric_storage_lock_transient_key() );
	if ( false === $locked_time ) {
		return false;
	}
	return microtime( true ) - floatval( $locked_time ) < $ttl;
}
