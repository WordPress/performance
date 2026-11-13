<?php
/**
 * Context provider base class.
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
 * Base class for AI Performance Advisor context providers.
 *
 * A context provider contributes one bounded section of structured data to the
 * payload sent to the AI model. Providers are intentionally small and isolated
 * so that the analysis context can be extended (or trimmed) without changing the
 * analyzer. This is also the seam that read-only Abilities (see issue #2441) can
 * plug into in a future version.
 *
 * @since 1.0.0
 */
abstract class AIPA_Context_Provider {

	/**
	 * Returns the unique key under which this provider's data is stored.
	 *
	 * @since 1.0.0
	 *
	 * @return string Provider key (lowercase, snake_case).
	 */
	abstract public function get_key(): string;

	/**
	 * Returns a human-readable label describing what this provider sends.
	 *
	 * Shown to the user before analysis so they know what data will be transmitted.
	 *
	 * @since 1.0.0
	 *
	 * @return string Provider label.
	 */
	abstract public function get_label(): string;

	/**
	 * Whether this provider can contribute data in the current environment.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether the provider is available.
	 */
	public function is_available(): bool {
		return true;
	}

	/**
	 * Collects this provider's context data.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Structured, AI-consumable data.
	 */
	abstract public function collect(): array;
}
