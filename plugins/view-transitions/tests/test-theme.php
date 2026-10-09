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
	 * Data provider for test_plvt_inject_animation_duration.
	 *
	 * @return array<string, array{
	 *     css: string,
	 *     duration: int,
	 *     expected: string
	 * }>
	 */
	public function data_plvt_inject_animation_duration(): array {
		return array(
			'with_existing_css_and_custom_duration'     => array(
				'css'      => '::view-transition-old(root) { opacity: 1; }',
				'duration' => 500,
				'expected' => '::view-transition-old(root) { opacity: 1; }::view-transition-group(*) { --plvt-view-transition-animation-duration: 0.5s; }',
			),
			'with_existing_css_and_default_duration'    => array(
				'css'      => '::view-transition-new(root) { opacity: 0; }',
				'duration' => 400,
				'expected' => '::view-transition-new(root) { opacity: 0; }::view-transition-group(*) { --plvt-view-transition-animation-duration: 0.4s; }',
			),
			'with_empty_css_and_custom_duration'        => array(
				'css'      => '',
				'duration' => 300,
				'expected' => '::view-transition-group(*) { animation-duration: 0.3s; }',
			),
			'with_zero_duration_defaults_to_1000ms'     => array(
				'css'      => '',
				'duration' => 0,
				'expected' => '::view-transition-group(*) { animation-duration: 1s; }',
			),
			'with_negative_duration_defaults_to_1000ms' => array(
				'css'      => '::view-transition-old(root) { opacity: 1; }',
				'duration' => -500,
				'expected' => '::view-transition-old(root) { opacity: 1; }::view-transition-group(*) { --plvt-view-transition-animation-duration: 1s; }',
			),
			'with_standard_1000ms_duration'             => array(
				'css'      => '',
				'duration' => 1000,
				'expected' => '::view-transition-group(*) { animation-duration: 1s; }',
			),
		);
	}

	/**
	 * @covers ::plvt_inject_animation_duration
	 * @dataProvider data_plvt_inject_animation_duration
	 *
	 * @param string $css      Input CSS.
	 * @param int    $duration Animation duration in milliseconds.
	 * @param string $expected Expected output CSS.
	 */
	public function test_plvt_inject_animation_duration( string $css, int $duration, string $expected ): void {
		$this->assertSame( $expected, plvt_inject_animation_duration( $css, $duration ) );
	}

	/**
	 * @covers ::plvt_sanitize_view_transitions_theme_support
	 */
	public function test_plvt_sanitize_view_transitions_theme_support(): void {
		// Test when theme support is not registered.
		remove_theme_support( 'view-transitions' );
		plvt_sanitize_view_transitions_theme_support();
		$this->assertFalse( get_theme_support( 'view-transitions' ) );

		// Test when theme support is registered as boolean true (defaults are applied).
		add_theme_support( 'view-transitions' );
		plvt_sanitize_view_transitions_theme_support();
		$support = get_theme_support( 'view-transitions' );
		$this->assertIsArray( $support );
		$this->assertSame( 'fade', $support['default-animation'] );
		$this->assertSame( 400, $support['default-animation-duration'] );
		$this->assertIsArray( $support['global-transition-names'] );
		$this->assertIsArray( $support['post-transition-names'] );

		// Test when invalid non-array transition names are supplied (enforces array type).
		add_theme_support(
			'view-transitions',
			array(
				'default-animation'       => 'slide',
				'global-transition-names' => 'invalid-string',
				'post-transition-names'   => null,
			)
		);
		plvt_sanitize_view_transitions_theme_support();
		$support = get_theme_support( 'view-transitions' );
		$this->assertIsArray( $support );
		$this->assertSame( 'slide', $support['default-animation'] );
		$this->assertSame( array(), $support['global-transition-names'] );
		$this->assertSame( array(), $support['post-transition-names'] );
	}

	/**
	 * @covers ::plvt_register_view_transition_animations
	 */
	public function test_plvt_register_view_transition_animations(): void {
		$registry     = new PLVT_View_Transition_Animation_Registry();
		$action_fired = false;
		$callback     = static function ( $reg ) use ( &$action_fired, $registry ): void {
			if ( $reg === $registry ) {
				$action_fired = true;
			}
		};

		add_action( 'plvt_register_view_transition_animations', $callback );
		plvt_register_view_transition_animations( $registry );
		remove_action( 'plvt_register_view_transition_animations', $callback );

		$this->assertTrue( $action_fired );
		$this->assertTrue( $registry->use_animation_global_transition_names( 'fade' ) );
		$this->assertTrue( $registry->use_animation_post_transition_names( 'fade' ) );
		$this->assertFalse( $registry->use_animation_global_transition_names( 'wipe' ) );
		$this->assertTrue( $registry->use_animation_post_transition_names( 'wipe' ) );
	}
}
