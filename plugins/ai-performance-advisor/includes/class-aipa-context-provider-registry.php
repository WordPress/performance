<?php
/**
 * Context provider registry.
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
 * Collects context providers and assembles the analysis payload.
 *
 * @since 1.0.0
 */
class AIPA_Context_Provider_Registry {

	/**
	 * Registered providers.
	 *
	 * @since 1.0.0
	 * @var AIPA_Context_Provider[]
	 */
	private array $providers = array();

	/**
	 * Registers a context provider.
	 *
	 * @since 1.0.0
	 *
	 * @param AIPA_Context_Provider $provider Provider instance.
	 */
	public function register( AIPA_Context_Provider $provider ): void {
		$this->providers[ $provider->get_key() ] = $provider;
	}

	/**
	 * Unregisters a context provider by key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key Provider key.
	 */
	public function unregister( string $key ): void {
		unset( $this->providers[ $key ] );
	}

	/**
	 * Returns the registered, currently-available providers.
	 *
	 * @since 1.0.0
	 *
	 * @return AIPA_Context_Provider[] Available providers keyed by provider key.
	 */
	public function get_available_providers(): array {
		return array_filter(
			$this->providers,
			static function ( AIPA_Context_Provider $provider ): bool {
				return $provider->is_available();
			}
		);
	}

	/**
	 * Returns labels for the available providers, for display to the user.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Provider labels.
	 */
	public function get_available_labels(): array {
		return array_values(
			array_map(
				static function ( AIPA_Context_Provider $provider ): string {
					return $provider->get_label();
				},
				$this->get_available_providers()
			)
		);
	}

	/**
	 * Collects context from every available provider.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The assembled context payload.
	 */
	public function collect(): array {
		$context = array();

		foreach ( $this->get_available_providers() as $key => $provider ) {
			$data = $provider->collect();
			if ( count( $data ) > 0 ) {
				$context[ $key ] = $data;
			}
		}

		/**
		 * Filters the full context payload sent to the AI model.
		 *
		 * Use this to redact, add, or otherwise adjust the data transmitted for
		 * analysis. The array is keyed by provider key.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $context The assembled context payload.
		 */
		return apply_filters( 'aipa_context', $context );
	}
}
