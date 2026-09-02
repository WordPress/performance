<?php
/**
 * Tests for the View Transitions plugin includes/settings.php file.
 *
 * @package view-transitions
 * @group   view-transitions
 */

class Test_ViewTransitions_Settings extends WP_UnitTestCase {

	/**
	 * @covers ::plvt_sanitize_setting
	 */
	public function test_plvt_sanitize_setting_returns_defaults_for_invalid_input(): void {
		$this->assertSame( plvt_get_setting_default(), plvt_sanitize_setting( null ) );
		$this->assertSame( plvt_get_setting_default(), plvt_sanitize_setting( 'string' ) );
		$this->assertSame( plvt_get_setting_default(), plvt_sanitize_setting( 123 ) );
	}

	/**
	 * @covers ::plvt_sanitize_setting
	 */
	public function test_plvt_sanitize_setting_clamps_duration_minimum(): void {
		$input  = array( 'default_transition_animation_duration' => 50 );
		$result = plvt_sanitize_setting( $input );
		$this->assertSame( PLVT_MIN_ANIMATION_DURATION, $result['default_transition_animation_duration'] );
	}

	/**
	 * @covers ::plvt_sanitize_setting
	 */
	public function test_plvt_sanitize_setting_clamps_duration_maximum(): void {
		$input  = array( 'default_transition_animation_duration' => 10000 );
		$result = plvt_sanitize_setting( $input );
		$this->assertSame( PLVT_MAX_ANIMATION_DURATION, $result['default_transition_animation_duration'] );
	}

	/**
	 * @covers ::plvt_sanitize_setting
	 */
	public function test_plvt_sanitize_setting_accepts_valid_duration(): void {
		$input  = array( 'default_transition_animation_duration' => 500 );
		$result = plvt_sanitize_setting( $input );
		$this->assertSame( 500, $result['default_transition_animation_duration'] );
	}

	/**
	 * @covers ::plvt_sanitize_setting
	 */
	public function test_plvt_sanitize_setting_handles_negative_duration(): void {
		$input  = array( 'default_transition_animation_duration' => -500 );
		$result = plvt_sanitize_setting( $input );
		// absint converts negative to positive, then clamps.
		$this->assertSame( 500, $result['default_transition_animation_duration'] );
	}

	/**
	 * @covers ::plvt_sanitize_setting
	 */
	public function test_plvt_sanitize_setting_handles_string_duration(): void {
		$input  = array( 'default_transition_animation_duration' => '750' );
		$result = plvt_sanitize_setting( $input );
		$this->assertSame( 750, $result['default_transition_animation_duration'] );
	}

	/**
	 * @covers ::plvt_get_setting_default
	 */
	public function test_plvt_get_setting_default_has_valid_duration(): void {
		$defaults = plvt_get_setting_default();
		$this->assertArrayHasKey( 'default_transition_animation_duration', $defaults );
		$this->assertIsInt( $defaults['default_transition_animation_duration'] );
		$this->assertGreaterThanOrEqual( PLVT_MIN_ANIMATION_DURATION, $defaults['default_transition_animation_duration'] );
		$this->assertLessThanOrEqual( PLVT_MAX_ANIMATION_DURATION, $defaults['default_transition_animation_duration'] );
	}

	/**
	 * @covers ::plvt_sanitize_setting
	 */
	public function test_plvt_sanitize_setting_validates_animation_type(): void {
		$input  = array( 'default_transition_animation' => 'invalid-animation' );
		$result = plvt_sanitize_setting( $input );
		$this->assertSame( 'fade', $result['default_transition_animation'] );

		$input  = array( 'default_transition_animation' => 'slide-from-right' );
		$result = plvt_sanitize_setting( $input );
		$this->assertSame( 'slide-from-right', $result['default_transition_animation'] );
	}
}
