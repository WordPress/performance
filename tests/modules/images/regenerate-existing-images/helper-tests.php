<?php
/**
 * Tests for regenerate-existing-images module helper.php.
 *
 * @package performance-lab
 * @group regenerate-existing-images
 */

class Regenerate_Existing_Images_Helper_Test extends WP_UnitTestCase {

	public function test_perflab_create_background_job() {
		$name = 'test';
		$data = array( 'post' => 123 );
		$job  = perflab_create_background_job( $name, $data );

		$this->assertInstanceOf( Perflab_Background_Job::class, $job );
		$job_data = $job->get_data();
		$this->assertSame( $data, $job_data );
		$job_status = $job->get_status();
		$this->assertEquals( 'perflab_job_queued', $job_status );
	}

	public function test_perflab_delete_background_job() {
		$name    = 'test';
		$data    = array( 'post' => 123 );
		$job     = perflab_create_background_job( $name, $data );
		$deleted = perflab_delete_background_job( $job->get_id() );

		$this->assertTrue( $deleted );
	}
}
