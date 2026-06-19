<?php
/**
 * Test double: a context provider that reports itself as unavailable.
 *
 * @package ai-performance-advisor
 */

declare( strict_types = 1 );

if ( ! class_exists( 'AIPA_Unavailable_Context_Provider' ) ) {
	/**
	 * A provider that is never available and therefore never collected.
	 */
	class AIPA_Unavailable_Context_Provider extends AIPA_Context_Provider {

		/**
		 * {@inheritDoc}
		 *
		 * @return string Provider key.
		 */
		public function get_key(): string {
			return 'unavailable';
		}

		/**
		 * {@inheritDoc}
		 *
		 * @return string Provider label.
		 */
		public function get_label(): string {
			return 'Unavailable provider';
		}

		/**
		 * {@inheritDoc}
		 *
		 * @return bool Always false.
		 */
		public function is_available(): bool {
			return false;
		}

		/**
		 * {@inheritDoc}
		 *
		 * @return array<string, mixed> A payload that should never be collected.
		 */
		public function collect(): array {
			return array( 'should' => 'never appear' );
		}
	}
}
