<?php
/**
 * Helper functions for the AI Performance Advisor plugin.
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
 * Determines whether AI text generation is currently available.
 *
 * This requires WordPress 7.0+ (for the AI Client API), AI support to be enabled
 * for the request, and at least one connected provider that supports text generation.
 *
 * @since 1.0.0
 *
 * @return bool Whether AI recommendations can be generated.
 */
function aipa_is_ai_available(): bool {
	/**
	 * Short-circuits the AI availability check.
	 *
	 * Returning a boolean from this filter bypasses the built-in detection, which is
	 * useful for forcing the advisor on or off (for example when a host manages
	 * provider availability itself, or in tests).
	 *
	 * @since 1.0.0
	 *
	 * @param bool|null $pre Null to run the built-in detection, or a boolean to force the result.
	 */
	$pre = apply_filters( 'aipa_pre_is_ai_available', null );
	if ( is_bool( $pre ) ) {
		return $pre;
	}

	static $available = null;
	if ( null !== $available ) {
		return $available;
	}

	if ( ! function_exists( 'wp_ai_client_prompt' ) || ! function_exists( 'wp_supports_ai' ) ) {
		$available = false;
		return $available;
	}

	if ( ! wp_supports_ai() ) {
		$available = false;
		return $available;
	}

	try {
		$available = wp_ai_client_prompt( 'ping' )->is_supported_for_text_generation();
	} catch ( \Throwable $e ) {
		$available = false;
	}

	return $available;
}

/**
 * Returns the default plugin settings.
 *
 * @since 1.0.0
 *
 * @return array{ include_pagespeed: bool, pagespeed_api_key: string } Default settings.
 */
function aipa_get_default_settings(): array {
	return array(
		'include_pagespeed' => true,
		'pagespeed_api_key' => '',
	);
}

/**
 * Returns the plugin settings, merged with defaults.
 *
 * @since 1.0.0
 *
 * @return array{ include_pagespeed: bool, pagespeed_api_key: string } Settings.
 */
function aipa_get_settings(): array {
	$settings = get_option( 'aipa_settings', array() );
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}
	$merged = array_merge( aipa_get_default_settings(), $settings );

	return array(
		'include_pagespeed' => (bool) ( $merged['include_pagespeed'] ?? true ),
		'pagespeed_api_key' => (string) ( $merged['pagespeed_api_key'] ?? '' ),
	);
}

/**
 * Returns the list of valid recommendation severities, ordered most to least urgent.
 *
 * @since 1.0.0
 *
 * @return string[] Severity slugs.
 */
function aipa_get_severities(): array {
	return array( 'critical', 'recommended', 'good', 'info' );
}

/**
 * Returns the list of valid recommendation categories.
 *
 * @since 1.0.0
 *
 * @return string[] Category slugs.
 */
function aipa_get_categories(): array {
	return array( 'images', 'caching', 'scripts', 'navigation', 'server', 'database', 'other' );
}

/**
 * Sanitizes and validates raw recommendation data returned by the AI model.
 *
 * Untrusted model output is normalized into a predictable, escaped-on-render shape.
 * Invalid entries are dropped; results are sorted by severity.
 *
 * @since 1.0.0
 *
 * @param mixed $raw Decoded model output. Expected to be a list of recommendation arrays.
 * @return array<int, array{id: string, title: string, severity: string, category: string, summary: string, details: string, evidence: string[], action: array{settings_url: string, ability: array{name: string, args: array<string, mixed>}|null}|null}> Sanitized recommendations.
 */
function aipa_sanitize_recommendations( $raw ): array {
	// Allow either a bare list or an object with a "recommendations" key.
	if ( is_array( $raw ) && isset( $raw['recommendations'] ) && is_array( $raw['recommendations'] ) ) {
		$raw = $raw['recommendations'];
	}

	if ( ! is_array( $raw ) ) {
		return array();
	}

	$severities = aipa_get_severities();
	$categories = aipa_get_categories();
	$clean      = array();

	foreach ( $raw as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}

		$title   = isset( $item['title'] ) && is_string( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';
		$summary = isset( $item['summary'] ) && is_string( $item['summary'] ) ? sanitize_text_field( $item['summary'] ) : '';
		if ( '' === $title || '' === $summary ) {
			continue;
		}

		$severity = isset( $item['severity'] ) && in_array( $item['severity'], $severities, true ) ? $item['severity'] : 'recommended';
		$category = isset( $item['category'] ) && in_array( $item['category'], $categories, true ) ? $item['category'] : 'other';

		$id = isset( $item['id'] ) && is_string( $item['id'] ) ? sanitize_key( $item['id'] ) : sanitize_key( $title );
		if ( '' === $id ) {
			$id = 'recommendation';
		}

		// Details may contain limited Markdown; keep as plain text and let the renderer format it safely.
		$details = isset( $item['details'] ) && is_string( $item['details'] ) ? wp_kses_post( $item['details'] ) : '';

		$evidence = array();
		if ( isset( $item['evidence'] ) && is_array( $item['evidence'] ) ) {
			foreach ( $item['evidence'] as $line ) {
				if ( is_string( $line ) && '' !== $line ) {
					$evidence[] = sanitize_text_field( $line );
				}
			}
		}

		$clean[] = array(
			'id'       => $id,
			'title'    => $title,
			'severity' => $severity,
			'category' => $category,
			'summary'  => $summary,
			'details'  => $details,
			'evidence' => $evidence,
			'action'   => aipa_sanitize_recommendation_action( $item['action'] ?? null ),
		);
	}

	// Sort by severity order (critical first).
	$order = array_flip( $severities );
	usort(
		$clean,
		static function ( array $a, array $b ) use ( $order ): int {
			return ( $order[ $a['severity'] ] ?? 99 ) <=> ( $order[ $b['severity'] ] ?? 99 );
		}
	);

	return $clean;
}

/**
 * Sanitizes the optional, forward-looking "action" payload on a recommendation.
 *
 * In v1 only `settings_url` is honored for display. The `ability` field is parsed
 * and preserved for forward compatibility, but is only retained when the named
 * ability is actually registered (which never happens in v1).
 *
 * @since 1.0.0
 *
 * @param mixed $action Raw action payload.
 * @return array{settings_url: string, ability: array{name: string, args: array<string, mixed>}|null}|null Sanitized action or null.
 */
function aipa_sanitize_recommendation_action( $action ): ?array {
	if ( ! is_array( $action ) ) {
		return null;
	}

	$settings_url = '';
	if ( isset( $action['settings_url'] ) && is_string( $action['settings_url'] ) ) {
		// Only allow same-site admin URLs.
		$candidate = esc_url_raw( $action['settings_url'] );
		if ( '' !== $candidate && 0 === strpos( $candidate, admin_url() ) ) {
			$settings_url = $candidate;
		}
	}

	$ability = null;
	if (
		isset( $action['ability']['name'] ) &&
		is_string( $action['ability']['name'] ) &&
		function_exists( 'wp_has_ability' ) &&
		wp_has_ability( $action['ability']['name'] )
	) {
		$args    = isset( $action['ability']['args'] ) && is_array( $action['ability']['args'] ) ? $action['ability']['args'] : array();
		$ability = array(
			'name' => $action['ability']['name'],
			'args' => $args,
		);
	}

	if ( '' === $settings_url && null === $ability ) {
		return null;
	}

	return array(
		'settings_url' => $settings_url,
		'ability'      => $ability,
	);
}

/**
 * Computes a stable cache key fragment for a given context payload.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $context The assembled context payload.
 * @return string An md5 hash of the context.
 */
function aipa_get_context_hash( array $context ): string {
	return md5( (string) wp_json_encode( $context ) );
}

/**
 * Builds the context provider registry with the default providers registered.
 *
 * @since 1.0.0
 *
 * @return AIPA_Context_Provider_Registry The populated registry.
 */
function aipa_get_context_registry(): AIPA_Context_Provider_Registry {
	$registry = new AIPA_Context_Provider_Registry();

	$registry->register( new AIPA_Provider_Environment() );
	$registry->register( new AIPA_Provider_Site_Health() );
	$registry->register( new AIPA_Provider_Site_Health_Tests() );
	$registry->register( new AIPA_Provider_PageSpeed() );
	$registry->register( new AIPA_Provider_Optimization_Detective() );

	/**
	 * Fires after the default context providers are registered.
	 *
	 * Use this to register additional context providers (subclasses of
	 * AIPA_Context_Provider) or to unregister the defaults.
	 *
	 * @since 1.0.0
	 *
	 * @param AIPA_Context_Provider_Registry $registry The context provider registry.
	 */
	do_action( 'aipa_register_context_providers', $registry );

	return $registry;
}
