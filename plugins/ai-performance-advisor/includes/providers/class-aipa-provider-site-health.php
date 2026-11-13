<?php
/**
 * Site Health debug-data context provider.
 *
 * @package ai-performance-advisor
 *
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides performance-relevant server, database, media, and constant data.
 *
 * Reuses WordPress core's Site Health debug data ({@see WP_Debug_Data}), excluding
 * any fields flagged as private (keys, salts, secrets) and limiting the payload to
 * the performance-relevant sections.
 *
 * @since 1.0.0
 */
class AIPA_Provider_Site_Health extends AIPA_Context_Provider {

	/**
	 * Debug-data sections to include, mapped to output keys.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	private const SECTIONS = array(
		'wp-core'      => 'wp_core',
		'wp-server'    => 'server',
		'wp-database'  => 'database',
		'wp-media'     => 'media',
		'wp-constants' => 'constants',
	);

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @return string Provider key.
	 */
	public function get_key(): string {
		return 'site_health';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @return string Provider label.
	 */
	public function get_label(): string {
		return __( 'Server, PHP, database, media, and configuration details from Site Health (private fields excluded)', 'ai-performance-advisor' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Site Health data.
	 */
	public function collect(): array {
		if ( ! class_exists( 'WP_Debug_Data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! function_exists( 'get_core_updates' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}

		$all_sections = WP_Debug_Data::debug_data();

		$data = array();
		foreach ( self::SECTIONS as $section_key => $output_key ) {
			if ( ! isset( $all_sections[ $section_key ] ) || ! is_array( $all_sections[ $section_key ] ) ) {
				continue;
			}
			$flattened = $this->flatten_section( $all_sections[ $section_key ] );
			if ( count( $flattened ) > 0 ) {
				$data[ $output_key ] = $flattened;
			}
		}

		return $data;
	}

	/**
	 * Flattens a WP_Debug_Data section into a label => value map, dropping private fields.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $section A debug-data section with a 'fields' array.
	 * @return array<string, string> Label => value pairs.
	 */
	private function flatten_section( array $section ): array {
		$out = array();

		if ( ! isset( $section['fields'] ) || ! is_array( $section['fields'] ) ) {
			return $out;
		}

		foreach ( $section['fields'] as $field_key => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			if ( (bool) ( $field['private'] ?? false ) ) {
				continue;
			}

			$label = isset( $field['label'] ) && is_string( $field['label'] ) ? $field['label'] : (string) $field_key;
			$value = $field['value'] ?? '';

			if ( is_bool( $value ) ) {
				$value = $value ? 'true' : 'false';
			} elseif ( is_array( $value ) ) {
				$value = (string) wp_json_encode( $value );
			} elseif ( ! is_scalar( $value ) ) {
				$value = '';
			}

			$out[ $label ] = (string) $value;
		}

		return $out;
	}
}
