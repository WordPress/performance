<?php
/**
 * Optimization Detective context provider.
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
 * Provides a summary of Optimization Detective activity, when that plugin is active.
 *
 * This is intentionally a lightweight summary (active status, version, and the number
 * of measured URLs). Richer per-element URL Metric extraction is left for a future
 * version so that v1 does not couple tightly to Optimization Detective internals.
 *
 * @since 1.0.0
 */
class AIPA_Provider_Optimization_Detective extends AIPA_Context_Provider {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @return string Provider key.
	 */
	public function get_key(): string {
		return 'optimization_detective';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @return string Provider label.
	 */
	public function get_label(): string {
		return __( 'Optimization Detective status and number of measured URLs (only if active)', 'ai-performance-advisor' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether Optimization Detective is active.
	 */
	public function is_available(): bool {
		return defined( 'OPTIMIZATION_DETECTIVE_VERSION' ) && class_exists( 'OD_URL_Metrics_Post_Type' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Optimization Detective summary.
	 */
	public function collect(): array {
		$measured = 0;
		$counts   = wp_count_posts( OD_URL_Metrics_Post_Type::SLUG );
		if ( is_object( $counts ) ) {
			$measured = (int) array_sum( array_map( 'intval', (array) $counts ) );
		}

		return array(
			'active'             => true,
			'version'            => OPTIMIZATION_DETECTIVE_VERSION,
			'measured_url_count' => $measured,
		);
	}
}
