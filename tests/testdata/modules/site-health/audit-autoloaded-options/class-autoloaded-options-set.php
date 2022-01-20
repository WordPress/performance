<?php
/**
 * @package performance-lab
 * @since 1.0.0
 */

/**
 * Class Autoloaded_Options_Set sets extra options.
 *
 * @since 1.0.0
 */
class Autoloaded_Options_Set {

	/**
	 * Sets an autoloaded option.
	 *
	 * @param int $bytes bytes to load in options.
	 */
	public static function set_autoloaded_option( $bytes = 800000 ) {
		$heavy_option_string = self::random_string_generator( $bytes );
		add_option( 'test_set_autoloaded_option', $heavy_option_string );
	}

	/**
	 * Generate random string with certain $length.
	 *
	 * @param int $length Length of string to create.
	 * @return string
	 */
	protected static function random_string_generator( $length ) {
		$seed        = 'abcd123';
		$length_seed = strlen( $seed );
		$string      = '';
		for ( $x = 0; $x < $length; $x++ ) {
			$string .= $seed[ rand( 0, $length_seed - 1 ) ];
		}
		return $string;
	}
}

