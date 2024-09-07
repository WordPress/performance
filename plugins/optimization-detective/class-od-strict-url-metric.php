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
		return self::falsify_additional_properties( parent::get_json_schema() );
	}

	/**
	 * Recursively processes the schema to ensure that all objects have additionalProperties set to false.
	 *
	 * @since n.e.x.t
	 *
	 * @param mixed $schema Schema.
	 * @return mixed Processed schema.
	 */
	private static function falsify_additional_properties( $schema ) {
		if ( ! isset( $schema['type'] ) ) {
			return $schema;
		}
		if ( 'object' === $schema['type'] ) {
			$schema['additionalProperties'] = false;
			if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
				$schema['properties'] = array_map(
					static function ( $property_schema ) {
						return self::falsify_additional_properties( $property_schema );
					},
					$schema['properties']
				);
			}
		} elseif ( 'array' === $schema['type'] && isset( $schema['items'] ) && is_array( $schema['items'] ) ) {
			$schema['items'] = self::falsify_additional_properties( $schema['items'] );
		}
		return $schema;
	}
}
