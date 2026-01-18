<?php
/**
 * Tests for the View Transitions plugin includes/admin.php file.
 *
 * @package view-transitions
 * @group   view-transitions
 */

class Test_ViewTransitions_Admin extends WP_UnitTestCase {

	/**
	 * @covers ::plvt_print_view_transitions_admin_style
	 */
	public function test_plvt_print_view_transitions_admin_style_disabled(): void {
		update_option( 'plvt_view_transitions', array( 'enable_admin_transitions' => false ) );

		ob_start();
		plvt_print_view_transitions_admin_style();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * @covers ::plvt_print_view_transitions_admin_style
	 */
	public function test_plvt_print_view_transitions_admin_style_enabled(): void {
		update_option(
			'plvt_view_transitions',
			array(
				'enable_admin_transitions'              => true,
				'default_transition_animation_duration' => 500,
			)
		);

		ob_start();
		plvt_print_view_transitions_admin_style();
		$output = ob_get_clean();

		$this->assertStringContainsString( '@view-transition { navigation: auto; }', $output );
		$this->assertStringContainsString( 'animation-duration: 0.5s', $output );
	}

	/**
	 * @covers ::plvt_print_view_transitions_admin_style
	 */
	public function test_plvt_print_view_transitions_admin_style_uses_default_duration(): void {
		update_option(
			'plvt_view_transitions',
			array( 'enable_admin_transitions' => true )
		);

		ob_start();
		plvt_print_view_transitions_admin_style();
		$output = ob_get_clean();

		// Default duration is 400ms = 0.4s.
		$this->assertStringContainsString( 'animation-duration: 0.4s', $output );
	}
}
