<?php
/**
 * Image Loading Optimization: ILO_Data_Validation_Exception class
 *
 * @package image-loading-optimization
 * @since n.e.x.t
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exception thrown when failing to validate URL metrics data.
 *
 * @since n.e.x.t
 * @access private
 */
final class ILO_Data_Validation_Exception extends Exception {}
