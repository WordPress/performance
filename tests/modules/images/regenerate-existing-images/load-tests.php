<?php
/**
 * Tests for regenerate-existing-images module load.php.
 *
 * @package performance-lab
 * @group   regenerate-existing-images
 */


class Regenerate_Existing_Images_Load_Test extends WP_UnitTestCase {

	/**
	 * Test that background_job taxonomy is present in system.
	 *
	 * @return void
	 */
	public function test_background_job_taxonomy_exists() {
		$taxonomies = get_taxonomies();

		$this->assertIsArray( $taxonomies );
		$this->assertContains( 'background_job', $taxonomies );
	}

	/**
	 * Ensure taxonomy is non public and would not show in REST.
	 *
	 * @return void
	 */
	public function test_background_job_taxonomy_is_non_public() {
		$job_tax = get_taxonomy( 'background_job' );

		$this->assertFalse( $job_tax->public );
		$this->assertFalse( $job_tax->show_in_rest );
		$this->assertFalse( $job_tax->hierarchical );
	}
}
