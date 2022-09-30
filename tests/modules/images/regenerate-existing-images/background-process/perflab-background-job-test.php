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

	/**
	 * Job instance.
	 *
	 * @var Perflab_Background_Job
	 */
	private $job;

	public function set_up() {
		$this->job = perflab_create_background_job( 'test' );
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
		$result = $this->job->set_status( 'invalid_status' );
		$this->assertFalse( $result );
	}

	public function test_create_job() {
		$this->assertInstanceOf( Perflab_Background_Job::class, $this->job );
	}

	/**
	 * @covers ::should_run
	 */
	public function test_job_should_run_for_job() {
		$run = $this->job->should_run();
		$this->assertTrue( $run );
	}

	/**
	 * @covers ::should_run
	 */
	public function test_job_should_not_run_for_completed_job() {
		// Mark job as complete.
		$this->job->set_status( 'completed' );
		$run = $this->job->should_run();

		$this->assertFalse( $run );
	}

	/**
	 * @covers ::lock
	 * @covers ::unlock
	 */
	public function test_lock_unlock() {
		$time = time();
		$this->job->lock( $time );
		$lock_time = get_term_meta( $this->job->get_id(), 'perflab_job_lock', true );
		$this->assertSame( absint( $lock_time ), $time );
		$this->job->unlock();
		$lock_time = get_term_meta( $this->job->get_id(), 'perflab_job_lock', true );
		$this->assertEmpty( $lock_time );
	}

	/**
	 * @covers ::get_start_time
	 */
	public function test_get_start_time() {
		$time = time();
		$this->job->lock( $time );
		$lock_time = get_term_meta( $this->job->get_id(), 'perflab_job_lock', true );
		$this->assertSame( absint( $lock_time ), $time );

		$start_time = $this->job->get_start_time();
		$this->assertEquals( $start_time, $lock_time );
		$this->assertEquals( $start_time, $time );
		$this->assertEquals( $time, $lock_time );
	}

	public function test_max_attempts_filter() {
		$filter_ran = false;
		add_filter(
			'perflab_job_max_attempts_allowed',
			function( $value ) use ( &$filter_ran ) {
				$filter_ran = true;
				return $value;
			}
		);
		$this->job->should_run();

		$this->assertTrue( $filter_ran );
	}

	public function test_max_attempts_limit_reached() {
		$before_attempts_limit = $this->job->should_run();
		update_term_meta( $this->job->get_id(), $this->job::META_KEY_JOB_ATTEMPTS, 2 );
		add_filter(
			'perflab_job_max_attempts_allowed',
			function() {
				return 1;
			}
		);
		$after_attempts_limit = $this->job->should_run();

		$this->assertTrue( $before_attempts_limit );
		$this->assertFalse( $after_attempts_limit );
	}

	/**
	 * @covers ::set_status
	 * @dataProvider job_provider
	 */
	public function test_set_status_false_for_valid_status( $status ) {
		$this->assertTrue( $status );
	}

	public function job_provider() {
		$job = perflab_create_background_job( 'test' );
		return array(
			'running status'   => array( $job->set_status( 'running' ) ),
			'partial status'   => array( $job->set_status( 'partial' ) ),
			'failed status'    => array( $job->set_status( 'failed' ) ),
			'completed status' => array( $job->set_status( 'completed' ) ),
		);
	}
}
