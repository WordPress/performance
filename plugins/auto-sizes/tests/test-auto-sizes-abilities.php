<?php
/**
 * Tests for Enhanced Responsive Images Abilities API registrations.
 *
 * @package auto-sizes
 */

/**
 * Tests for auto-sizes abilities.
 *
 * @package auto-sizes
 */
class Test_Auto_Sizes_Abilities extends WP_UnitTestCase {

	/**
	 * Tests that the ability category and compute-sizes-attribute ability are registered.
	 *
	 * @covers ::auto_sizes_register_ability_category
	 * @covers ::auto_sizes_register_compute_sizes_ability
	 */
	public function test_compute_sizes_ability_is_registered(): void {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'WordPress Abilities API is not available.' );
		}

		$this->assertTrue( wp_has_ability_category( 'performance-lab-images' ) );

		$ability = wp_get_ability( 'auto-sizes/compute-sizes-attribute' );
		$this->assertInstanceOf( WP_Ability::class, $ability );
		$this->assertSame( 'performance-lab-images', $ability->get_category() );

		$meta = $ability->get_meta();
		$this->assertTrue( $meta['show_in_rest'] );
		$this->assertTrue( $meta['mcp']['public'] );
		$this->assertTrue( $meta['annotations']['readonly'] );
		$this->assertFalse( $meta['annotations']['destructive'] );
		$this->assertTrue( $meta['annotations']['idempotent'] );
	}

	/**
	 * Tests permission callback for diagnostic auto-sizes abilities.
	 *
	 * @covers ::auto_sizes_ability_permission_check
	 */
	public function test_permission_callback(): void {
		wp_set_current_user( 0 );
		$this->assertFalse( auto_sizes_ability_permission_check() );

		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );
		$this->assertFalse( auto_sizes_ability_permission_check() );

		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );
		$this->assertTrue( auto_sizes_ability_permission_check() );
	}

	/**
	 * Tests compute-sizes-attribute returns WP_Error for invalid inputs.
	 *
	 * @covers ::auto_sizes_ability_compute_sizes_attribute
	 */
	public function test_compute_sizes_returns_wp_error_for_invalid_input(): void {
		$result = auto_sizes_ability_compute_sizes_attribute( null );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'auto_sizes_invalid_input', $result->get_error_code() );

		$result = auto_sizes_ability_compute_sizes_attribute( array() );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'auto_sizes_missing_dimensions', $result->get_error_code() );

		$result = auto_sizes_ability_compute_sizes_attribute( array( 'attachment_id' => -1 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'auto_sizes_invalid_attachment_id', $result->get_error_code() );

		$result = auto_sizes_ability_compute_sizes_attribute( array( 'resize_width' => -10 ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'auto_sizes_invalid_resize_width', $result->get_error_code() );

		$result = auto_sizes_ability_compute_sizes_attribute(
			array(
				'resize_width' => 500,
				'align'        => 'invalid',
			)
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'auto_sizes_invalid_alignment', $result->get_error_code() );

		$result = auto_sizes_ability_compute_sizes_attribute(
			array(
				'resize_width'  => 500,
				'max_alignment' => 'invalid',
			)
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'auto_sizes_invalid_max_alignment', $result->get_error_code() );

		$result = auto_sizes_ability_compute_sizes_attribute(
			array(
				'resize_width'             => 500,
				'container_relative_width' => 1.5,
			)
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'auto_sizes_invalid_container_relative_width', $result->get_error_code() );
	}

	/**
	 * Tests compute-sizes-attribute returns expected structure with block theme vs classic theme.
	 *
	 * @covers ::auto_sizes_ability_compute_sizes_attribute
	 */
	public function test_compute_sizes_returns_result(): void {
		$result = auto_sizes_ability_compute_sizes_attribute(
			array(
				'resize_width' => 400,
				'align'        => 'wide',
			)
		);

		$this->assertIsArray( $result );
		$this->assertSame( wp_is_block_theme(), $result['is_block_theme'] );
		$this->assertSame( 0, $result['attachment_id'] );
		$this->assertSame( 'full', $result['size'] );
		$this->assertSame( 'wide', $result['align'] );
		$this->assertSame( 400, $result['resize_width'] );
		$this->assertSame( 'full', $result['max_alignment'] );
		$this->assertSame( 1.0, $result['container_relative_width'] );

		if ( wp_is_block_theme() ) {
			$this->assertIsString( $result['sizes'] );
		} else {
			$this->assertNull( $result['sizes'] );
		}
	}

	/**
	 * Tests that get-version-status ability is registered with expected metadata.
	 *
	 * @covers ::auto_sizes_register_get_version_status_ability
	 */
	public function test_get_version_status_ability_is_registered(): void {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'WordPress Abilities API is not available.' );
		}

		$ability = wp_get_ability( 'auto-sizes/get-version-status' );
		$this->assertInstanceOf( WP_Ability::class, $ability );
		$this->assertSame( 'performance-lab-images', $ability->get_category() );

		$meta = $ability->get_meta();
		$this->assertTrue( $meta['show_in_rest'] );
		$this->assertTrue( $meta['mcp']['public'] );
		$this->assertTrue( $meta['annotations']['readonly'] );
		$this->assertFalse( $meta['annotations']['destructive'] );
		$this->assertTrue( $meta['annotations']['idempotent'] );
	}

	/**
	 * Tests get-version-status returns expected plugin, core, and layout status.
	 *
	 * @covers ::auto_sizes_ability_get_version_status
	 */
	public function test_get_version_status_returns_expected_data(): void {
		$result = auto_sizes_ability_get_version_status();

		$this->assertSame( IMAGE_AUTO_SIZES_VERSION, $result['plugin_version'] );
		$this->assertSame( get_bloginfo( 'version' ), $result['wp_version'] );
		$this->assertTrue( $result['core_auto_sizes_supported'] );
		$this->assertFalse( $result['polyfill_active'] );
		$this->assertSame( wp_is_block_theme(), $result['is_block_theme'] );
		$this->assertArrayHasKey( 'content_size', $result['layout'] );
		$this->assertArrayHasKey( 'wide_size', $result['layout'] );

		if ( function_exists( 'wp_get_ability' ) ) {
			$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );
			wp_set_current_user( $editor_id );

			$ability = wp_get_ability( 'auto-sizes/get-version-status' );
			$this->assertInstanceOf( WP_Ability::class, $ability );
			$this->assertSame( $result, $ability->execute() );
		}
	}
}
