<?php
/**
 * Optimization Detective: OD_Strict_URL_Metric class
 *
 * @package optimization-detective
 * @since n.e.x.t
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Representation of the measurements taken from a single client's visit to a specific URL without additionalProperties allowed.
 *
 * This is used exclusively in the REST API endpoint for capturing new URL metrics to prevent invalid additional data from being
 * submitted in the request. For URL metrics which have been stored the looser OD_URL_Metric class is used instead.
 *
 * @since n.e.x.t
 * @access private
 */
final class OD_Strict_URL_Metric extends OD_URL_Metric {

	/**
	 * Gets JSON schema for URL Metric without additionalProperties.
	 *
	 * @since n.e.x.t
	 *
	 * @return array<string, mixed> Schema.
	 */
	public static function get_json_schema(): array {
		return self::set_additional_properties_to_false( parent::get_json_schema() );
	}

	/**
	 * Recursively processes the schema to ensure that all objects have additionalProperties set to false.
	 *
	 * This is a forked version of `rest_default_additional_properties_to_false()` which isn't being used itself because
	 * it does not override `additionalProperties` to be false, but rather only sets it when it is empty.
	 *
	 * @since n.e.x.t
	 * @see rest_default_additional_properties_to_false()
	 *
	 * @param mixed $schema Schema.
	 * @return mixed Processed schema.
	 */
	private static function set_additional_properties_to_false( $schema ) {
		if ( ! isset( $schema['type'] ) ) {
			return $schema;
		}

		$type = (array) $schema['type'];

		if ( in_array( 'object', $type, true ) ) {
			if ( isset( $schema['properties'] ) ) {
				foreach ( $schema['properties'] as $key => $child_schema ) {
					$schema['properties'][ $key ] = self::set_additional_properties_to_false( $child_schema );
				}
			}

			if ( isset( $schema['patternProperties'] ) ) {
				foreach ( $schema['patternProperties'] as $key => $child_schema ) {
					$schema['patternProperties'][ $key ] = self::set_additional_properties_to_false( $child_schema );
				}
			}

			$schema['additionalProperties'] = false;
		}

		if ( in_array( 'array', $type, true ) ) {
			if ( isset( $schema['items'] ) ) {
				$schema['items'] = self::set_additional_properties_to_false( $schema['items'] );
			}
		}

		return $schema;
	}
}
