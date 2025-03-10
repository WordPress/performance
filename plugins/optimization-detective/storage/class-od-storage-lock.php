<?php
/**
 * Optimization Detective: OD_Storage_Lock class
 *
 * @package optimization-detective
 * @since 0.1.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Class containing logic for locking storage for new URL Metrics.
 *
 * @since 0.1.0
 * @access private
 */
final class OD_Storage_Lock {

	/**
	 * Capability for being able to store a URL Metric now.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const STORE_URL_METRIC_NOW_CAPABILITY = 'od_store_url_metric_now';

	/**
	 * Adds hooks.
	 *
	 * @since 1.0.0
	 */
	public static function add_hooks(): void {
		add_filter( 'user_has_cap', array( __CLASS__, 'filter_user_has_cap' ) );
	}

	/**
	 * Filters `user_has_cap` to grant the `od_store_url_metric_now` capability to users who can `manage_options` by default.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, bool>|mixed $allcaps Capability names mapped to boolean values for whether the user has that capability.
	 * @return array<string, bool> Capability names mapped to boolean values for whether the user has that capability.
	 */
	public static function filter_user_has_cap( $allcaps ): array {
		if ( ! is_array( $allcaps ) ) {
			$allcaps = array();
		}
		if ( isset( $allcaps['manage_options'] ) ) {
			$allcaps['od_store_url_metric_now'] = $allcaps['manage_options'];
		}
		return $allcaps;
	}

	/**
	 * Gets the TTL (in seconds) for the URL Metric storage lock.
	 *
	 * @since 0.1.0
	 *
	 * @return int<0, max> TTL in seconds, greater than or equal to zero. A value of zero means that the storage lock should be disabled and thus that transients must not be used.
	 */
	public static function get_ttl(): int {
		$ttl = current_user_can( self::STORE_URL_METRIC_NOW_CAPABILITY ) ? 0 : MINUTE_IN_SECONDS;

		/**
		 * Filters how long the current IP is locked from submitting another URL metric storage REST API request.
		 *
		 * Filtering the TTL to zero will disable any URL Metric storage locking. This is useful, for example, to disable
		 * locking when a user is logged-in with code like the following:
		 *
		 *     add_filter( 'od_metrics_storage_lock_ttl', static function ( int $ttl ): int {
		 *         return is_user_logged_in() ? 0 : $ttl;
		 *     } );
		 *
		 * By default, the TTL is zero (0) for authorized users and sixty (60) for everyone else. Whether the current
		 * user is authorized is determined by whether the user has the `od_store_url_metric_now` capability. This
		 * custom capability by default maps to the `manage_options` primitive capability via the `user_has_cap` filter.
		 *
		 * @since 0.1.0
		 * @since 1.0.0 This now defaults to zero (0) for authorized users.
		 *
		 * @param int $ttl TTL. Defaults to 60, except zero (0) for authorized users.
		 */
		$ttl = (int) apply_filters( 'od_url_metric_storage_lock_ttl', $ttl );
		return max( 0, $ttl );
	}

	/**
	 * Gets transient key for locking URL Metric storage (for the current IP).
	 *
	 * @since 0.1.0
	 *
	 * @return non-empty-string Transient key.
	 */
	public static function get_transient_key(): string {
		$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
		return 'url_metrics_storage_lock_' . wp_hash( $ip_address );
	}

	/**
	 * Sets URL Metric storage lock (for the current IP).
	 *
	 * If the storage lock TTL is greater than zero, then a transient is set with the current timestamp and expiring at TTL
	 * seconds. Otherwise, if the current TTL is zero, then any transient is deleted.
	 *
	 * @since 0.1.0
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
	 * Checks whether URL Metric storage is locked (for the current IP).
	 *
	 * @since 0.1.0
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
