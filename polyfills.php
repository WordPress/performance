<?php
/**
 * These functions are either introduced in WP Core in latest version or
 * available in different versions. For simplicity for future, we can remove them
 * if they are supported in WP Core versions which this plugin supports.
 *
 * @package performance-lab
 * @since 1.2.0
 */

// WP Filesize function.
if ( ! function_exists( 'wp_filesize' ) ) {
	/**
	 * Wrapper for PHP filesize with filters and casting the result as an integer.
	 *
	 * This function was introduced in WP 6.0, for backward compatibility the
	 * function is added as backup here.
	 *
	 * @param string $path Path to the file.
	 *
	 * @return int The size of the file in bytes, or 0 in the event of an error.
	 * @since 1.2.0
	 *
	 * @link https://www.php.net/manual/en/function.filesize.php
	 */
	function wp_filesize( $path ) {
		/**
		 * Filters the result of wp_filesize before the PHP function is run.
		 *
		 * @param null|int $size The unfiltered value. Returning an int from the callback bypasses the filesize call.
		 * @param string $path Path to the file.
		 *
		 * @since 1.2.0
		 */
		$size = apply_filters( 'pre_wp_filesize', null, $path );

		if ( is_int( $size ) ) {
			return $size;
		}

		$size = file_exists( $path ) ? (int) filesize( $path ) : 0;

		/**
		 * Filters the size of the file.
		 *
		 * @param int $size The result of PHP filesize on the file.
		 * @param string $path Path to the file.
		 *
		 * @since 1.2.0
		 */
		return (int) apply_filters( 'wp_filesize', $size, $path );
	}
}
