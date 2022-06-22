<?php
/**
 * Helper functions used by module.
 *
 * @package performance-lab
 * @since 1.0.0
 */

/**
 * Gets total of enqueued scripts.
 *
 * @since 1.0.0
 *
 * @return int|false Number of total scripts or false if transient hasn't been set.
 */
function perflab_aea_get_total_enqueued_scripts() {
	$enqueued_scripts      = false;
	$list_enqueued_scripts = get_transient( 'aea_enqueued_front_page_scripts' );
	if ( $list_enqueued_scripts ) {
		$enqueued_scripts = count( $list_enqueued_scripts );
	}
	return $enqueued_scripts;
}

/**
 * Gets total size in bytes of Enqueued Scripts.
 *
 * @since 1.0.0
 *
 * @return int|false Byte Total size or false if transient hasn't been set.
 */
function perflab_aea_get_total_size_bytes_enqueued_scripts() {
	$total_size            = false;
	$list_enqueued_scripts = get_transient( 'aea_enqueued_front_page_scripts' );
	if ( $list_enqueued_scripts ) {
		$total_size = 0;
		foreach ( $list_enqueued_scripts as $enqueued_script ) {
			$total_size += $enqueued_script['size'];
		}
	}
	return $total_size;
}

/**
 * Gets total of enqueued styles.
 *
 * @since 1.0.0
 *
 * @return int|false Number of total styles or false if transient hasn't been set.
 */
function perflab_aea_get_total_enqueued_styles() {
	$enqueued_styles      = false;
	$list_enqueued_styles = get_transient( 'aea_enqueued_front_page_styles' );
	if ( $list_enqueued_styles ) {
		$enqueued_styles = count( $list_enqueued_styles );
	}
	return $enqueued_styles;
}

/**
 * Gets total size in bytes of Enqueued Styles.
 *
 * @since 1.0.0
 *
 * @return int|false Byte Total size or false if transient hasn't been set.
 */
function perflab_aea_get_total_size_bytes_enqueued_styles() {
	$total_size           = false;
	$list_enqueued_styles = get_transient( 'aea_enqueued_front_page_styles' );
	if ( $list_enqueued_styles ) {
		$total_size = 0;
		foreach ( $list_enqueued_styles as $enqueued_style ) {
			$total_size += $enqueued_style['size'];
		}
	}
	return $total_size;
}

/**
 * Convert full URL paths to absolute paths.
 * Covers Standard WP configuration, wp-content outside WP directories and subdirectories.
 * Ex: https://example.com/content/themes/, https://example.com/wp/wp-includes/
 *
 * @since 1.0.0
 *
 * @param string $resource_url URl resource link.
 * @return string Returns absolute path to the resource.
 */
function perflab_aea_get_path_from_resource_url( $resource_url ) {
	if ( ! $resource_url ) {
		return '';
	}

	// Different content folder ex. /content/.
	if ( 0 === strpos( $resource_url, content_url() ) ) {
		return WP_CONTENT_DIR . substr( $resource_url, strlen( content_url() ) );
	}

	// wp-content in a subdirectory. ex. /blog/wp-content/.
	$site_url = untrailingslashit( site_url() );
	if ( 0 === strpos( $resource_url, $site_url ) ) {
		return untrailingslashit( ABSPATH ) . substr( $resource_url, strlen( $site_url ) );
	}

	// Standard wp-content configuration.
	return untrailingslashit( ABSPATH ) . wp_make_link_relative( $resource_url );
}
