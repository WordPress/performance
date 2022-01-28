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
 *
 * @since 1.0.0
 *
 * @param string $resource_url URl resource link.
 * @return string Returns absolute path to the resource.
 */
function perflab_aea_get_path_from_resource_url( $resource_url ) {
	return ABSPATH . wp_make_link_relative( $resource_url );
}

/**
 * If file exists, returns its size.
 *
 * @since 1.0.0
 *
 * @param string $file_src Path to the file.
 * @return int Returns size if file exists, 0 if it doesn't.
 */
function perflab_aea_get_resource_file_size( $file_src ) {
	return file_exists( $file_src ) ? filesize( $file_src ) : 0;
}

