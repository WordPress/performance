<?php
/**
 * Tests for the View Transitions plugin includes/theme.php file.
 *
 * @package view-transitions
 * @group   view-transitions
 */

class Test_ViewTransitions_Theme extends WP_UnitTestCase {

	/**
	 * @covers ::plvt_polyfill_theme_support
	 */
	public function test_plvt_polyfill_theme_support(): void {
		// Test polyfill without support registered.
		remove_theme_support( 'view-transitions' );
		plvt_polyfill_theme_support();
		$this->assertTrue( current_theme_supports( 'view-transitions' ) );
		$this->assertTrue( get_theme_support( 'view-transitions' ) );

		// Test polyfill does not override theme support arguments if already provided by the actual theme.
		add_theme_support( 'view-transitions', array( 'custom_key' => 'custom_value' ) );
		plvt_polyfill_theme_support();
		$this->assertTrue( current_theme_supports( 'view-transitions' ) );
		$this->assertSame( array( array( 'custom_key' => 'custom_value' ) ), get_theme_support( 'view-transitions' ) );
	}

	/**
	 * @covers ::plvt_load_view_transitions
	 * @covers ::plvt_sanitize_view_transitions_theme_support
	 */
	public function test_plvt_load_view_transitions(): void {
		// Clear up style if it is already registered.
		if ( wp_style_is( 'plvt-view-transitions', 'registered' ) ) {
			unset( wp_styles()->registered['plvt-view-transitions'] );
		}

		// Test that without theme support this does nothing.
		remove_theme_support( 'view-transitions' );
		plvt_load_view_transitions();
		$this->assertFalse( wp_style_is( 'plvt-view-transitions', 'registered' ) );
		$this->assertFalse( wp_style_is( 'plvt-view-transitions', 'enqueued' ) );

		// Test that with theme support it registers and enqueues the style.
		add_theme_support( 'view-transitions' );
		plvt_sanitize_view_transitions_theme_support(); // This must be called to sanitize the arguments (normally on 'init').
		plvt_load_view_transitions();
		$this->assertTrue( wp_style_is( 'plvt-view-transitions', 'registered' ) );
		$this->assertTrue( wp_style_is( 'plvt-view-transitions', 'enqueued' ) );
	}

	/**
	 * @covers ::plvt_load_view_transitions
	 * @covers ::plvt_sanitize_view_transitions_theme_support
	 * @covers ::plvt_inject_animation_duration
	 */
	public function test_plvt_load_view_transitions_injects_duration_for_additional_transition_stylesheets(): void {
		// Clear up style if it is already registered.
		if ( wp_style_is( 'plvt-view-transitions', 'registered' ) ) {
			unset( wp_styles()->registered['plvt-view-transitions'] );
		}

		remove_theme_support( 'view-transitions' );
		add_theme_support(
			'view-transitions',
			array(
				'default-animation'                 => 'fade',
				'default-animation-duration'        => 500,
				'chronological-forwards-animation'  => 'slide-from-right',
				'chronological-backwards-animation' => 'slide-from-left',
			)
		);

		plvt_sanitize_view_transitions_theme_support();
		plvt_load_view_transitions();

		$styles = wp_styles()->registered['plvt-view-transitions']->extra['after'] ?? array();

		$this->assertIsArray( $styles );
		$this->assertNotEmpty( $styles );
		$this->assertStringContainsString( '--plvt-view-transition-animation-duration: 0.5s;', implode( '', $styles ) );
		$this->assertStringContainsString( 'html:active-view-transition-type(chronological-forwards)', implode( '', $styles ) );
	}
}
