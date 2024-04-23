<?php
/**
 * Optimization Detective: OD_Storage_Lock class
 *
 * @package optimization-detective
 * @since 0.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class containing logic for locking storage for new URL metrics.
 *
 * @since 0.1.0
 * @access private
 */
final class OD_Storage_Lock {

	/**
	 * Gets the TTL (in seconds) for the URL metric storage lock.
	 *
	 * @since 0.1.0
	 * @access private
	 *
	 * @return int TTL in seconds, greater than or equal to zero. A value of zero means that the storage lock should be disabled and thus that transients must not be used.
	 */
	public static function get_ttl(): int {

		/**
		 * Filters how long a given IP is locked from submitting another metric-storage REST API request.
		 *
		 * Filtering the TTL to zero will disable any metric storage locking. This is useful, for example, to disable
		 * locking when a user is logged-in with code like the following:
		 *
		 *     add_filter( 'od_metrics_storage_lock_ttl', static function ( $ttl ) {
		 *         return is_user_logged_in() ? 0 : $ttl;
		 *     } );
		 *
		 * @since 0.1.0
		 *
		 * @param int $ttl TTL.
		 */
		$ttl = (int) apply_filters( 'od_url_metric_storage_lock_ttl', MINUTE_IN_SECONDS );
		return max( 0, $ttl );
	}

	/**
	 * Gets transient key for locking URL metric storage (for the current IP).
	 *
	 * @todo Should the URL be included in the key? Or should a user only be allowed to store one metric?
	 * @return string Transient key.
	 */
	public static function get_transient_key(): string {
		$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
		return 'url_metrics_storage_lock_' . wp_hash( $ip_address );
	}

	/**
	 * Sets URL metric storage lock (for the current IP).
	 *
	 * If the storage lock TTL is greater than zero, then a transient is set with the current timestamp and expiring at TTL
	 * seconds. Otherwise, if the current TTL is zero, then any transient is deleted.
	 *
	 * @since 0.1.0
	 * @access private
	 */
	public static function set_lock(): void {
		$ttl = self::get_ttl();
		$key = self::get_transient_key();
		if ( 0 === $ttl ) {
			delete_transient( $key );
		} else {
			set_transient( $key, microtime( true ), $ttl );
		}
	}

	/**
	 * Checks whether URL metric storage is locked (for the current IP).
	 *
	 * @since 0.1.0
	 * @access private
	 *
	 * @return bool Whether locked.
	 */
	public static function is_locked(): bool {
		$ttl = self::get_ttl();
		if ( 0 === $ttl ) {
			return false;
		}
		$locked_time = get_transient( self::get_transient_key() );
		if ( false === $locked_time ) {
			return false;
		}
		return microtime( true ) - floatval( $locked_time ) < $ttl;
	}
}
