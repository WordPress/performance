<?php
/**
 * PageSpeed Insights context provider.
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
 * Provides a compact PageSpeed Insights (Lighthouse) snapshot of the home page.
 *
 * Only a bounded subset of the PageSpeed Insights response is returned (overall
 * performance score, lab metrics, and the top opportunities), to keep the AI
 * payload small. Results are cached in a transient.
 *
 * @since 1.0.0
 */
class AIPA_Provider_PageSpeed extends AIPA_Context_Provider {

	const CACHE_KEY = 'aipa_pagespeed_cache';

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @return string Provider key.
	 */
	public function get_key(): string {
		return 'pagespeed';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @return string Provider label.
	 */
	public function get_label(): string {
		return __( 'A PageSpeed Insights (Lighthouse) snapshot of your home page', 'ai-performance-advisor' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether the PageSpeed provider is enabled.
	 */
	public function is_available(): bool {
		$settings = aipa_get_settings();
		return (bool) $settings['include_pagespeed'];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Compact PageSpeed Insights data.
	 */
	public function collect(): array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$settings     = aipa_get_settings();
		$query_params = array(
			'url'      => home_url( '/' ),
			'category' => 'performance',
			'strategy' => 'mobile',
		);
		if ( '' !== $settings['pagespeed_api_key'] ) {
			$query_params['key'] = $settings['pagespeed_api_key'];
		}

		$response = wp_remote_get(
			add_query_arg( $query_params, 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed' ),
			array( 'timeout' => 60 )
		);

		if ( is_wp_error( $response ) ) {
			return array( 'error' => $response->get_error_message() );
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return array( 'error' => sprintf( 'PageSpeed Insights returned HTTP %d.', (int) wp_remote_retrieve_response_code( $response ) ) );
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) || ! isset( $decoded['lighthouseResult'] ) || ! is_array( $decoded['lighthouseResult'] ) ) {
			return array( 'error' => 'Could not decode the PageSpeed Insights response.' );
		}

		$compact             = $this->compact_result( $decoded['lighthouseResult'] );
		$compact['url']      = home_url( '/' );
		$compact['strategy'] = 'mobile';

		set_transient( self::CACHE_KEY, $compact, 12 * HOUR_IN_SECONDS );

		return $compact;
	}

	/**
	 * Reduces a Lighthouse result to a compact, token-friendly summary.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $lhr The lighthouseResult object.
	 * @return array<string, mixed> Compact summary.
	 */
	private function compact_result( array $lhr ): array {
		$audits = isset( $lhr['audits'] ) && is_array( $lhr['audits'] ) ? $lhr['audits'] : array();

		$score = null;
		if ( isset( $lhr['categories']['performance']['score'] ) && is_numeric( $lhr['categories']['performance']['score'] ) ) {
			$score = (int) round( ( (float) $lhr['categories']['performance']['score'] ) * 100 );
		}

		$metric_keys = array(
			'first-contentful-paint',
			'largest-contentful-paint',
			'total-blocking-time',
			'cumulative-layout-shift',
			'speed-index',
			'interactive',
		);
		$metrics     = array();
		foreach ( $metric_keys as $metric_key ) {
			if ( isset( $audits[ $metric_key ]['displayValue'] ) && is_string( $audits[ $metric_key ]['displayValue'] ) ) {
				$metrics[ $metric_key ] = $audits[ $metric_key ]['displayValue'];
			}
		}

		$opportunities = array();
		foreach ( $audits as $audit ) {
			if ( ! is_array( $audit ) ) {
				continue;
			}
			$is_opportunity = isset( $audit['details']['type'] ) && 'opportunity' === $audit['details']['type'];
			$audit_score    = isset( $audit['score'] ) && is_numeric( $audit['score'] ) ? (float) $audit['score'] : 1.0;
			if ( $is_opportunity && $audit_score < 0.9 && isset( $audit['title'] ) ) {
				$opportunities[] = array(
					'title'        => sanitize_text_field( (string) $audit['title'] ),
					'displayValue' => isset( $audit['displayValue'] ) ? sanitize_text_field( (string) $audit['displayValue'] ) : '',
				);
			}
			if ( count( $opportunities ) >= 10 ) {
				break;
			}
		}

		return array(
			'performance_score' => $score,
			'metrics'           => $metrics,
			'opportunities'     => $opportunities,
		);
	}
}
