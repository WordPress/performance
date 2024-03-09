<?php
/**
 * Optimization Detective: OD_Data_Validation_Exception class
 *
 * @package optimization-detective
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
final class OD_Data_Validation_Exception extends Exception {}
