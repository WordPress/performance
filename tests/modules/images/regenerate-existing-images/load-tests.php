<?php
/**
 * Tests for regenerate-existing-images module load.php.
 *
 * @package performance-lab
 * @group regenerate-existing-images
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
		$this->assertFalse( $job_tax->show_in_nav_menus );
		$this->assertFalse( $job_tax->show_in_quick_edit );
		$this->assertFalse( $job_tax->show_admin_column );
		$this->assertFalse( $job_tax->query_var );

		// show_in_menu and show_ui should be true.
		$this->assertTrue( $job_tax->show_in_menu );
		$this->assertTrue( $job_tax->show_ui );
	}

	/**
	 * @covers ::perflab_status_check_cron
	 */
	public function test_perflab_status_check_cron() {
		$scheduled = wp_next_scheduled( 'perflab_background_process_status_check' );

		$this->assertIsInt( $scheduled );
	}
}
