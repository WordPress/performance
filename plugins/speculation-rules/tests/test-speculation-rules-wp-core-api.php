<?php
/**
 * Tests for speculation-rules WP Core API.
 *
 * @package speculation-rules
 */

class Test_Speculation_Rules_WP_Core_API extends WP_UnitTestCase {

	/**
	 * @covers ::plsr_filter_speculation_rules_configuration
	 */
	public function test_plsr_filter_speculation_rules_configuration_with_regular(): void {
		$this->assertSame(
			array(
				'mode'      => 'prerender',
				'eagerness' => 'moderate',
			),
			plsr_filter_speculation_rules_configuration(
				array(
					'mode'      => 'prefetch',
					'eagerness' => 'conservative',
				)
			)
		);
	}

	/**
	 * @covers ::plsr_filter_speculation_rules_configuration
	 */
	public function test_plsr_filter_speculation_rules_configuration_with_invalid(): void {
		$this->assertSame(
			array(
				'mode'      => 'prerender',
				'eagerness' => 'moderate',
			),
			plsr_filter_speculation_rules_configuration( 'neither-an-array-nor-null' )
		);
	}

	/**
	 * @covers ::plsr_filter_speculation_rules_configuration
	 */
	public function test_plsr_filter_speculation_rules_configuration_with_null(): void {
		$this->assertNull( plsr_filter_speculation_rules_configuration( null ) );
	}

	/**
	 * @covers ::plsr_filter_speculation_rules_configuration
	 */
	public function test_plsr_filter_speculation_rules_configuration_with_pretty_permalinks_filter(): void {
		// Providing null while pretty permalinks are disabled should be respected.
		$this->disable_pretty_permalinks();
		$this->assertNull( plsr_filter_speculation_rules_configuration( null ) );

		// Unless the filter to enable speculative loading despite that is set to true.
		add_filter( 'plsr_enabled_without_pretty_permalinks', '__return_true' );
		$this->assertSame(
			array(
				'mode'      => 'prerender',
				'eagerness' => 'moderate',
			),
			plsr_filter_speculation_rules_configuration( null )
		);
	}

	/**
	 * @covers ::plsr_filter_speculation_rules_exclude_paths
	 */
	public function test_plsr_filter_speculation_rules_exclude_paths_with_regular(): void {
		$base_rules = array( '/membership-areas/*' );

		$this->assertSame( $base_rules, plsr_filter_speculation_rules_exclude_paths( $base_rules, 'prefetch' ) );

		add_filter(
			'plsr_speculation_rules_href_exclude_paths',
			static function ( $rules ) {
				$rules[] = '/cart/*';
				return $rules;
			}
		);

		$this->assertSame(
			array_merge( $base_rules, array( '/cart/*' ) ),
			plsr_filter_speculation_rules_exclude_paths( $base_rules, 'prefetch' )
		);
	}

	/**
	 * @covers ::plsr_filter_speculation_rules_exclude_paths
	 */
	public function test_plsr_filter_speculation_rules_exclude_paths_with_invalid(): void {
		$this->assertSame( array( '/personalized/*' ), plsr_filter_speculation_rules_exclude_paths( '/personalized/*', 'prefetch' ) );
	}

	private function disable_pretty_permalinks(): void {
		update_option( 'permalink_structure', '' );
	}
}
