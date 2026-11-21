<?php
/**
 * Site Health tests context provider.
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
 * Provides the results of plugin-contributed direct Site Health tests.
 *
 * Tests are gathered via the `site_status_tests` filter rather than WP_Site_Health,
 * so only plugin-registered tests are returned. Only direct tests with a directly
 * callable test callback are run. This naturally captures performance-focused tests
 * added by plugins (for example Performance Lab's autoloaded-options and
 * enqueued-assets audits).
 *
 * @since 1.0.0
 */
class AIPA_Provider_Site_Health_Tests extends AIPA_Context_Provider {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @return string Provider key.
	 */
	public function get_key(): string {
		return 'site_health_tests';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @return string Provider label.
	 */
	public function get_label(): string {
		return __( 'Results of performance-related Site Health tests', 'ai-performance-advisor' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Test results keyed by test slug.
	 */
	public function collect(): array {
		/** This filter is documented in wp-admin/includes/class-wp-site-health.php. */
		$tests = apply_filters(
			'site_status_tests',
			array(
				'direct' => array(),
				'async'  => array(),
			)
		);

		if ( ! is_array( $tests ) || ! isset( $tests['direct'] ) || ! is_array( $tests['direct'] ) ) {
			return array();
		}

		$results = array();
		foreach ( $tests['direct'] as $slug => $test ) {
			if ( ! is_array( $test ) || ! isset( $test['test'] ) || ! is_callable( $test['test'] ) ) {
				continue;
			}

			try {
				$result = call_user_func( $test['test'] );
			} catch ( \Throwable $e ) {
				continue;
			}

			if ( ! is_array( $result ) ) {
				continue;
			}

			$results[ sanitize_key( (string) $slug ) ] = array(
				'label'       => isset( $result['label'] ) ? wp_strip_all_tags( (string) $result['label'] ) : '',
				'status'      => isset( $result['status'] ) ? (string) $result['status'] : '',
				'description' => isset( $result['description'] ) ? wp_strip_all_tags( (string) $result['description'] ) : '',
			);
		}

		return $results;
	}
}
