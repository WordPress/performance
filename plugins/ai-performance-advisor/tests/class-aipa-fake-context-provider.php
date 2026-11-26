<?php
/**
 * Test double: a hermetic context provider returning a fixed payload.
 *
 * @package ai-performance-advisor
 */

declare( strict_types = 1 );

if ( ! class_exists( 'AIPA_Fake_Context_Provider' ) ) {
	/**
	 * A hermetic context provider that returns a small, fixed payload.
	 */
	class AIPA_Fake_Context_Provider extends AIPA_Context_Provider {

		/**
		 * {@inheritDoc}
		 *
		 * @return string Provider key.
		 */
		public function get_key(): string {
			return 'fake';
		}

		/**
		 * {@inheritDoc}
		 *
		 * @return string Provider label.
		 */
		public function get_label(): string {
			return 'Fake provider';
		}

		/**
		 * {@inheritDoc}
		 *
		 * @return array<string, mixed> Fixed payload.
		 */
		public function collect(): array {
			return array( 'note' => 'hermetic' );
		}
	}
}
