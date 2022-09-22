<?php
/**
 * Tests for background-job module.
 *
 * @package performance-lab
 * @group   background-process
 */

/**
 * Class Perflab_Background_Job_Test
 *
 * @coversDefaultClass Perflab_Background_Job
 * @group regenerate-existing-images
 */
class Perflab_Background_Job_Test extends WP_UnitTestCase {

	public function test_class_constants_exists() {
		$job_class = Perflab_Background_Job::class;

		$this->assertTrue( defined( $job_class . '::META_KEY_JOB_NAME' ) );
		$this->assertTrue( defined( $job_class . '::META_KEY_JOB_DATA' ) );
		$this->assertTrue( defined( $job_class . '::META_KEY_JOB_ATTEMPTS' ) );
		$this->assertTrue( defined( $job_class . '::META_KEY_JOB_LOCK' ) );
		$this->assertTrue( defined( $job_class . '::META_KEY_JOB_ERRORS' ) );
		$this->assertTrue( defined( $job_class . '::META_KEY_JOB_STATUS' ) );
		$this->assertTrue( defined( $job_class . '::META_KEY_JOB_COMPLETED_AT' ) );
		$this->assertTrue( defined( $job_class . '::JOB_STATUS_QUEUED' ) );
		$this->assertTrue( defined( $job_class . '::JOB_STATUS_RUNNING' ) );
		$this->assertTrue( defined( $job_class . '::JOB_STATUS_PARTIAL' ) );
		$this->assertTrue( defined( $job_class . '::JOB_STATUS_COMPLETE' ) );
		$this->assertTrue( defined( $job_class . '::JOB_STATUS_FAILED' ) );
	}

	public function test_class_constants_values() {
		$job_class = Perflab_Background_Job::class;

		$this->assertEquals( 'perflab_job_name', constant( $job_class . '::META_KEY_JOB_NAME' ) );
		$this->assertEquals( 'perflab_job_data', constant( $job_class . '::META_KEY_JOB_DATA' ) );
		$this->assertEquals( 'perflab_job_attempts', constant( $job_class . '::META_KEY_JOB_ATTEMPTS' ) );
		$this->assertEquals( 'perflab_job_lock', constant( $job_class . '::META_KEY_JOB_LOCK' ) );
		$this->assertEquals( 'perflab_job_errors', constant( $job_class . '::META_KEY_JOB_ERRORS' ) );
		$this->assertEquals( 'perflab_job_status', constant( $job_class . '::META_KEY_JOB_STATUS' ) );
		$this->assertEquals( 'perflab_job_completed_at', constant( $job_class . '::META_KEY_JOB_COMPLETED_AT' ) );
		$this->assertEquals( 'queued', constant( $job_class . '::JOB_STATUS_QUEUED' ) );
		$this->assertEquals( 'running', constant( $job_class . '::JOB_STATUS_RUNNING' ) );
		$this->assertEquals( 'partial', constant( $job_class . '::JOB_STATUS_PARTIAL' ) );
		$this->assertEquals( 'completed', constant( $job_class . '::JOB_STATUS_COMPLETE' ) );
		$this->assertEquals( 'failed', constant( $job_class . '::JOB_STATUS_FAILED' ) );
	}

	/**
	 * @covers ::set_status
	 */
	public function test_set_status_false_for_invalid_status() {
		$job = perflab_create_background_job( 'test', array( 'identifier' => 'testing_suite' ) );

		$result = $job->set_status( 'invalid_status' );
		$this->assertFalse( $result );
	}

	/**
	 * @covers ::set_status
	 */
	public function test_set_status_false_for_valid_status() {
		$job = perflab_create_background_job( 'test', array( 'identifier' => 'testing_suite' ) );

		$running_status  = $job->set_status( 'running' );
		$partial_status  = $job->set_status( 'partial' );
		$failed_status   = $job->set_status( 'failed' );
		$complete_status = $job->set_status( 'completed' );

		$this->assertTrue( $running_status );
		$this->assertTrue( $partial_status );
		$this->assertTrue( $failed_status );
		$this->assertTrue( $complete_status );
	}

	public function test_create_job() {
		$job_data = array(
			'identifier'       => 'test_suite',
			'post_id'          => 10,
			'some_random_data' => 'some_random_string',
		);

		$job = perflab_create_background_job( 'test_job', $job_data );

		$this->assertInstanceOf( Perflab_Background_Job::class, $job );
	}

	/**
	 * @covers ::should_run
	 */
	public function test_job_should_run_for_job() {
		$job = perflab_create_background_job( 'test', array( 'identifier' => 'testing_suite' ) );

		$run = $job->should_run();
		$this->assertTrue( $run );
	}

	/**
	 * @covers ::should_run
	 */
	public function test_job_should_not_run_for_completed_job() {
		$job = perflab_create_background_job( 'test', array( 'identifier' => 'testing_suite' ) );

		// Mark job as complete.
		$job->set_status( 'completed' );
		$run = $job->should_run();

		$this->assertFalse( $run );
	}

	/**
	 * @covers ::lock
	 * @covers ::unlock
	 */
	public function test_lock_unlock() {
		$job  = perflab_create_background_job( 'test', array( 'identifier' => 'testing_suite' ) );
		$time = time();
		$job->lock( $time );
		$lock_time = get_term_meta( $job->get_id(), 'perflab_job_lock', true );
		$this->assertSame( absint( $lock_time ), $time );
		$job->unlock();
		$lock_time = get_term_meta( $job->get_id(), 'perflab_job_lock', true );
		$this->assertEmpty( $lock_time );
	}

	/**
	 * @covers ::get_start_time
	 */
	public function test_get_start_time() {
		$job  = perflab_create_background_job( 'test', array( 'identifier' => 'testing_suite' ) );
		$time = time();
		$job->lock( $time );
		$lock_time = get_term_meta( $job->get_id(), 'perflab_job_lock', true );
		$this->assertSame( absint( $lock_time ), $time );

		$start_time = $job->get_start_time();
		$this->assertEquals( $start_time, $lock_time );
		$this->assertEquals( $start_time, $time );
		$this->assertEquals( $time, $lock_time );
	}

	public function test_max_attempts_filter() {
		$job        = perflab_create_background_job( 'test', array( 'identifier' => 'testing_suite' ) );
		$filter_ran = false;
		add_filter(
			'perflab_job_max_attempts_allowed',
			function( $value ) use ( &$filter_ran ) {
				$filter_ran = true;
				return $value;
			}
		);
		$job->should_run();

		$this->assertTrue( $filter_ran );
	}

	public function test_max_attempts_limit_reached() {
		$job                   = perflab_create_background_job( 'test', array( 'identifier' => 'testing_suite' ) );
		$before_attempts_limit = $job->should_run();
		update_term_meta( $job->get_id(), $job::META_KEY_JOB_ATTEMPTS, 2 );
		add_filter(
			'perflab_job_max_attempts_allowed',
			function() {
				return 1;
			}
		);
		$after_attempts_limit = $job->should_run();

		$this->assertTrue( $before_attempts_limit );
		$this->assertFalse( $after_attempts_limit );
	}
}
