<?php
/**
 * Tests for speculation-rules module.
 *
 * @package performance-lab
 * @group speculation-rules
 */

class Speculation_Rules_Tests extends WP_UnitTestCase {

	public function test_plsr_print_speculation_rules() {
		$output = get_echo( 'plsr_print_speculation_rules' );

		// Check the tag.
		$this->assertStringContainsString( '<script type="speculationrules">', $output );

		// Check that backslashes are correctly included.
		$this->assertStringContainsString( '\\?*#*', $output );
	}
}
