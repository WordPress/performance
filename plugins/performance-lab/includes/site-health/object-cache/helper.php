<?php
/**
 * Helper functions used for Object Cache Support Info.
 *
 * @package performance-lab
 * @since 2.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Callback for Object Cache Info fields.
 *
 * @return array Fields.
 * @since 3.3.0
 */
function object_cache_supported_fields() {
	return array(
		'extension'        => array(
			'label' => __( 'Extension', 'performance-lab' ),
			'value' => wp_get_cache_type(),
		),
		'multiple_gets'    => array(
			'label' => __( 'Multiple gets', 'performance-lab' ),
			'value' => wp_cache_supports( 'get_multiple' ) ? 'Enabled' : 'Disabled',
		),
		'multiple_sets'    => array(
			'label' => __( 'Multiple sets', 'performance-lab' ),
			'value' => wp_cache_supports( 'set_multiple' ) ? 'Enabled' : 'Disabled',
		),
		'multiple_deletes' => array(
			'label' => __( 'Multiple deletes', 'performance-lab' ),
			'value' => wp_cache_supports( 'delete_multiple' ) ? 'Enabled' : 'Disabled',
		),
		'flush_group'      => array(
			'label' => __( 'Flush group', 'performance-lab' ),
			'value' => wp_cache_supports( 'flush_group' ) ? 'Enabled' : 'Disabled',
		),
	);
}

/**
 * Attempts to determine which object cache is being used.
 *
 * Note that the guesses made by this function are based on the WP_Object_Cache classes
 * that define the 3rd party object cache extension. Changes to those classes could render
 * problems with this function's ability to determine which object cache is being used.
 *
 * @return string
 * @since 3.3.0
 */
function wp_get_cache_type() {
	global $_wp_using_ext_object_cache, $wp_object_cache;

	if ( ! empty( $_wp_using_ext_object_cache ) ) {
		// Test for Memcached PECL extension memcached object cache (https://github.com/tollmanz/wordpress-memcached-backend)
		if ( isset( $wp_object_cache->m ) && $wp_object_cache->m instanceof \Memcached ) {
			$message = 'Memcached';

			// Test for Memcache PECL extension memcached object cache (https://wordpress.org/extend/plugins/memcached/)
		} elseif ( isset( $wp_object_cache->mc ) ) {
			$is_memcache = true;
			foreach ( $wp_object_cache->mc as $bucket ) {
				if ( ! $bucket instanceof \Memcache && ! $bucket instanceof \Memcached ) {
					$is_memcache = false;
				}
			}

			if ( $is_memcache ) {
				$message = 'Memcache';
			}

			// Test for Xcache object cache (https://plugins.svn.wordpress.org/xcache/trunk/object-cache.php)
		} elseif ( $wp_object_cache instanceof \XCache_Object_Cache ) {
			$message = 'Xcache';

			// Test for WinCache object cache (https://wordpress.org/extend/plugins/wincache-object-cache-backend/)
		} elseif ( class_exists( 'WinCache_Object_Cache' ) ) {
			$message = 'WinCache';

			// Test for APC object cache (https://wordpress.org/extend/plugins/apc/)
		} elseif ( class_exists( 'APC_Object_Cache' ) ) {
			$message = 'APC';

			// Test for WP Redis (https://wordpress.org/plugins/wp-redis/)
		} elseif ( isset( $wp_object_cache->redis ) && $wp_object_cache->redis instanceof \Redis ) {
			$message = 'Redis';

			// Test for Redis Object Cache (https://wordpress.org/plugins/redis-cache/)
		} elseif ( method_exists( $wp_object_cache, 'redis_instance' ) && method_exists( $wp_object_cache,
				'redis_status' ) ) {
			$message = 'Redis';

			// Test for Object Cache Pro (https://objectcache.pro/)
		} elseif ( method_exists( $wp_object_cache, 'config' ) && method_exists( $wp_object_cache, 'connection' ) ) {
			$message = 'Redis';

			// Test for WP LCache Object cache (https://github.com/lcache/wp-lcache)
		} elseif ( isset( $wp_object_cache->lcache ) && $wp_object_cache->lcache instanceof \LCache\Integrated ) {
			$message = 'WP LCache';

		} elseif ( function_exists( 'w3_instance' ) ) {
			$config  = w3_instance( 'W3_Config' );
			$message = 'Unknown';

			if ( $config->get_boolean( 'objectcache.enabled' ) ) {
				$message = 'W3TC ' . $config->get_string( 'objectcache.engine' );
			}
		} else {
			$message = 'Unknown';
		}
	} else {
		$message = 'Disabled';
	}

	return $message;
}
