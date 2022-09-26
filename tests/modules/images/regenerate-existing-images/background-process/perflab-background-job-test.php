<?php
/**
 * Tests for background-job module.
 *
 * @package performance-lab
 * @group   regenerate-existing-images
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

		$this->assertTrue( defined( $job_class . '::META_KEY_JOB_ACTION' ) );
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

		$this->assertEquals( 'perflab_job_action', constant( $job_class . '::META_KEY_JOB_ACTION' ) );
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
		$job = perflab_create_background_job( 'test', array( 'action' => 'testing_suite' ) );

		$result = $job->set_status( 'invalid_status' );
		$this->assertFalse( $result );
	}

	/**
	 * @covers ::set_status
	 * @dataProvider job_provider
	 */
	public function test_set_status_false_for_valid_status( $job ) {
		$this->assertTrue( $job->set_status( 'running' ) );
		$this->assertTrue( $job->set_status( 'partial' ) );
		$this->assertTrue( $job->set_status( 'failed' ) );
		$this->assertTrue( $job->set_status( 'completed' ) );
	}

	public function test_create_job() {
		$job_data = array(
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
		$job = perflab_create_background_job( 'test' );
		$run = $job->should_run();
		$this->assertTrue( $run );
	}

	/**
	 * @covers ::should_run
	 * @dataProvider job_provider
	 */
	public function test_job_should_not_run_for_completed_job( $job ) {
		// Mark job as complete.
		$job->set_status( 'completed' );
		$run = $job->should_run();

		$this->assertFalse( $run );
	}

	/**
	 * @covers ::lock
	 * @covers ::unlock
	 * @dataProvider job_provider
	 */
	public function test_lock_unlock( $job ) {
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
	 * @dataProvider job_provider
	 */
	public function test_get_start_time( $job ) {
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
		$job        = perflab_create_background_job( 'test' );
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
		$job                   = perflab_create_background_job( 'test' );
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

	public function job_provider() {
		$job = perflab_create_background_job( 'test' );

		return array(
			array( $job ),
		);
	}

	public function max_attempts_10() {
		$this->attempt_filter_ran = true;

		return 10;
	}

	public function max_attempts_1() {
		return 1;
	}
}
