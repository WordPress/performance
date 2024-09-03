<?php
/**
 * Tests for avif image supported.
 *
 * @package webp-uploads
 */

use WebP_Uploads\Tests\TestCase;

class Test_Avif_Image_Support extends TestCase {
	public function test_avif_support(): void {
		$this->assertTrue( wp_image_editor_supports( array( 'mime_type' => 'image/avif' ) ), 'Mime type image/avif is not supported.' );
	}
}
