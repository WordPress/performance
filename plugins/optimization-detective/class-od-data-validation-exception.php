<?php
/**
 * Optimization Detective: OD_Data_Validation_Exception class
 *
 * @package optimization-detective
 * @since 0.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Exception thrown when failing to validate URL metrics data.
 *
 * @since 0.1.0
 * @access private
 */
final class OD_Data_Validation_Exception extends Exception {}
