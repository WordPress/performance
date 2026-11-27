<?php
/**
 * Tests for Image Placeholders Abilities API registrations.
 *
 * @package dominant-color-images
 */

use Dominant_Color_Images\Tests\TestCase;

/**
 * Tests for dominant-color-images abilities.
 *
 * @package dominant-color-images
 */
class Test_Dominant_Color_Abilities extends TestCase {

	/**
	 * Tests that the ability category and ability are registered with expected metadata.
	 *
	 * @covers ::dominant_color_register_ability_category
	 * @covers ::dominant_color_register_abilities
	 */
	public function test_ability_is_registered(): void {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'WordPress Abilities API is not available.' );
		}

		$this->assertTrue( wp_has_ability_category( 'performance-lab-images' ) );

		$ability = wp_get_ability( 'dominant-color-images/get-attachment-color' );
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
	 * Tests permission callback for public, private, and unauthenticated requests.
	 *
	 * @covers ::dominant_color_ability_get_attachment_color_permission
	 */
	public function test_permission_callback(): void {
		$attachment_id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
			)
		);

		$private_post_id    = self::factory()->post->create(
			array(
				'post_status' => 'private',
			)
		);
		$private_attachment = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_parent'    => $private_post_id,
			)
		);

		// Logged-out user cannot read.
		wp_set_current_user( 0 );
		$this->assertFalse( dominant_color_ability_get_attachment_color_permission( array( 'attachment_id' => $attachment_id ) ) );

		// Subscriber can read public attachment, but not attachment on a private post.
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );
		$this->assertTrue( dominant_color_ability_get_attachment_color_permission( array( 'attachment_id' => $attachment_id ) ) );
		$this->assertFalse( dominant_color_ability_get_attachment_color_permission( array( 'attachment_id' => $private_attachment ) ) );

		// Non-existent attachment falls back to 'read' cap so execute_callback can return 404.
		$this->assertTrue( dominant_color_ability_get_attachment_color_permission( array( 'attachment_id' => 999999 ) ) );
	}

	/**
	 * Tests execute callback returns dominant color and transparency for valid image attachment.
	 *
	 * @covers ::dominant_color_ability_get_attachment_color
	 */
	public function test_execute_returns_color_data(): void {
		$attachment_id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/png',
			)
		);

		wp_update_attachment_metadata(
			$attachment_id,
			array(
				'width'            => 100,
				'height'           => 100,
				'file'             => '2026/01/test.png',
				'dominant_color'   => 'ff00aa',
				'has_transparency' => true,
			)
		);

		$result = dominant_color_ability_get_attachment_color( array( 'attachment_id' => $attachment_id ) );

		$this->assertSame(
			array(
				'attachment_id'    => $attachment_id,
				'dominant_color'   => 'ff00aa',
				'has_transparency' => true,
			),
			$result
		);
	}

	/**
	 * Tests execute callback returns null values when dominant color metadata is absent.
	 *
	 * @covers ::dominant_color_ability_get_attachment_color
	 */
	public function test_execute_returns_null_when_metadata_missing(): void {
		$attachment_id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
			)
		);

		$result = dominant_color_ability_get_attachment_color( array( 'attachment_id' => $attachment_id ) );

		$this->assertSame(
			array(
				'attachment_id'    => $attachment_id,
				'dominant_color'   => null,
				'has_transparency' => null,
			),
			$result
		);
	}

	/**
	 * Tests execute callback returns WP_Error for invalid inputs and non-image attachments.
	 *
	 * @covers ::dominant_color_ability_get_attachment_color
	 */
	public function test_execute_returns_wp_error_for_invalid_input(): void {
		$invalid_inputs = array(
			null,
			array(),
			array( 'attachment_id' => 0 ),
			array( 'attachment_id' => -5 ),
			array( 'attachment_id' => '123' ),
		);

		foreach ( $invalid_inputs as $input ) {
			$result = dominant_color_ability_get_attachment_color( $input );
			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertSame( 'dominant_color_images_missing_attachment_id', $result->get_error_code() );
		}

		$pdf_attachment = self::factory()->post->create(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'application/pdf',
			)
		);

		$result = dominant_color_ability_get_attachment_color( array( 'attachment_id' => $pdf_attachment ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'dominant_color_images_invalid_attachment', $result->get_error_code() );

		$missing_result = dominant_color_ability_get_attachment_color( array( 'attachment_id' => 999999 ) );
		$this->assertInstanceOf( WP_Error::class, $missing_result );
		$this->assertSame( 'dominant_color_images_invalid_attachment', $missing_result->get_error_code() );
	}

	/**
	 * Tests indirect ability execution through WP_Ability::execute().
	 *
	 * @covers ::dominant_color_register_abilities
	 * @covers ::dominant_color_ability_get_attachment_color
	 */
	public function test_indirect_ability_execution(): void {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'WordPress Abilities API is not available.' );
		}

		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$attachment_id = self::factory()->attachment->create(
			array(
				'post_mime_type' => 'image/jpeg',
			)
		);
		wp_update_attachment_metadata(
			$attachment_id,
			array(
				'width'            => 50,
				'height'           => 50,
				'file'             => '2026/01/sample.jpg',
				'dominant_color'   => '123456',
				'has_transparency' => false,
			)
		);

		$ability = wp_get_ability( 'dominant-color-images/get-attachment-color' );
		$this->assertInstanceOf( WP_Ability::class, $ability );

		$result = $ability->execute( array( 'attachment_id' => $attachment_id ) );
		$this->assertSame(
			array(
				'attachment_id'    => $attachment_id,
				'dominant_color'   => '123456',
				'has_transparency' => false,
			),
			$result
		);
	}
}
