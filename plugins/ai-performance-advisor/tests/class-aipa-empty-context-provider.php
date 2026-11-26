<?php
/**
 * Test double: an available context provider that contributes no data.
 *
 * @package ai-performance-advisor
 */

declare( strict_types = 1 );

if ( ! class_exists( 'AIPA_Empty_Context_Provider' ) ) {
	/**
	 * A provider that is available but returns an empty payload.
	 */
	class AIPA_Empty_Context_Provider extends AIPA_Context_Provider {

		/**
		 * {@inheritDoc}
		 *
		 * @return string Provider key.
		 */
		public function get_key(): string {
			return 'empty';
		}

		/**
		 * {@inheritDoc}
		 *
		 * @return string Provider label.
		 */
		public function get_label(): string {
			return 'Empty provider';
		}

		/**
		 * {@inheritDoc}
		 *
		 * @return array<string, mixed> An empty payload.
		 */
		public function collect(): array {
			return array();
		}
	}
}
