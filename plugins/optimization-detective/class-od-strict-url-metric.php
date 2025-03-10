<?php
/**
 * Optimization Detective: OD_Strict_URL_Metric class
 *
 * @package optimization-detective
 * @since 0.6.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Representation of the measurements taken from a single client's visit to a specific URL without additionalProperties allowed.
 *
 * This is used exclusively in the REST API endpoint for capturing new URL Metrics to prevent invalid additional data from being
 * submitted in the request. For URL Metrics which have been stored the looser OD_URL_Metric class is used instead.
 *
 * @phpstan-import-type JSONSchema from OD_URL_Metric
 *
 * @since 0.6.0
 * @access private
 */
final class OD_Strict_URL_Metric extends OD_URL_Metric {

	/**
	 * Gets JSON schema for URL Metric without additionalProperties.
	 *
	 * @since 0.6.0
	 *
	 * @return JSONSchema Schema.
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
	 * @since 0.6.0
	 * @see rest_default_additional_properties_to_false()
	 *
	 * @phpstan-param JSONSchema $schema
	 *
	 * @param array<string, mixed> $schema Schema.
	 * @return JSONSchema Processed schema.
	 */
	private static function set_additional_properties_to_false( array $schema ): array {
		$type = (array) $schema['type'];

		if ( in_array( 'object', $type, true ) ) {
			if ( isset( $schema['properties'] ) ) {
				foreach ( $schema['properties'] as $key => $child_schema ) {
					if ( isset( $child_schema['type'] ) ) {
						$schema['properties'][ $key ] = self::set_additional_properties_to_false( $child_schema );
					}
				}
			}

			if ( isset( $schema['patternProperties'] ) ) {
				foreach ( $schema['patternProperties'] as $key => $child_schema ) {
					if ( isset( $child_schema['type'] ) ) {
						$schema['patternProperties'][ $key ] = self::set_additional_properties_to_false( $child_schema );
					}
				}
			}

			$schema['additionalProperties'] = false;
		}

		if ( in_array( 'array', $type, true ) ) {
			if ( isset( $schema['items'], $schema['items']['type'] ) ) {
				$schema['items'] = self::set_additional_properties_to_false( $schema['items'] );
			}
		}

		return $schema;
	}
}
