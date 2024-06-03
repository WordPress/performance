<?php
/**
 * Tests for optimization-detective class OD_Tag_Visitor_Registry.
 *
 * @package optimization-detective
 *
 * @coversDefaultClass OD_Tag_Visitor_Registry
 */
class Test_OD_Tag_Visitor_Registry extends WP_UnitTestCase {

	/**
	 * Tests methods.
	 *
	 * @covers ::register
	 * @covers ::unregister
	 * @covers ::get_registered
	 * @covers ::is_registered
	 * @covers ::getIterator
	 * @covers ::count
	 */
	public function test(): void {
		$registry = new OD_Tag_Visitor_Registry();
		$this->assertCount( 0, $registry );

		// Add img visitor.
		$this->assertFalse( $registry->is_registered( 'img' ) );
		$img_visitor = static function ( OD_HTML_Tag_Walker $walker ) {
			return $walker->get_tag() === 'IMG';
		};
		$registry->register( 'img', $img_visitor );
		$this->assertCount( 1, $registry );
		$this->assertTrue( $registry->is_registered( 'img' ) );

		// Add video visitor.
		$video_visitor = static function ( OD_HTML_Tag_Walker $walker ) {
			return $walker->get_tag() === 'VIDEO';
		};
		$registry->register( 'video', $video_visitor );
		$this->assertTrue( $registry->is_registered( 'video' ) );
		$this->assertCount( 2, $registry );

		// Check with unknown visitor.
		$registry->unregister( 'unknown' );
		$this->assertCount( 2, $registry );
		$this->assertFalse( $registry->is_registered( 'unknown' ) );
		$this->assertFalse( $registry->unregister( 'unknown' ) );

		// Override a visitor.
		$this->assertEqualSets(
			array( $img_visitor, $video_visitor ),
			iterator_to_array( $registry )
		);
		$img2_visitor = static function ( OD_HTML_Tag_Walker $walker ) {
			return $walker->get_tag() === 'IMG' || $walker->get_tag() === 'PICTURE';
		};
		$this->assertSame( $img_visitor, $registry->get_registered( 'img' ) );
		$registry->register( 'img', $img2_visitor );
		$this->assertSame( $img2_visitor, $registry->get_registered( 'img' ) );
		$this->assertEqualSets(
			array( $img2_visitor, $video_visitor ),
			iterator_to_array( $registry )
		);

		// Unregister a visitor.
		$this->assertTrue( $registry->unregister( 'video' ) );
		$this->assertFalse( $registry->unregister( 'video' ) );
		$this->assertEqualSets(
			array( $video_visitor ),
			iterator_to_array( $registry )
		);
	}
}
