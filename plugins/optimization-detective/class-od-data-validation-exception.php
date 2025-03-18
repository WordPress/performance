<?php
/**
 * Optimization Detective: OD_Data_Validation_Exception class
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
 * Exception thrown when failing to validate URL Metrics data.
 *
 * @since 0.1.0
 */
final class OD_Data_Validation_Exception extends Exception {}
