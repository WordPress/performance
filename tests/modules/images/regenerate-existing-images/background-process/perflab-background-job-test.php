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
 * @group background-process
 */
class Perflab_Background_Job_Test extends WP_UnitTestCase {
	/**
	 * Job instance.
	 *
	 * @var Perflab_Background_Job
	 */
	private $job;

	/**
	 * Runs before any test is executed inside class.
	 *
	 * @return void
	 */
	public static function set_up_before_class() {
		require_once PERFLAB_PLUGIN_DIR_PATH . 'modules/images/regenerate-existing-images/background-process/class-perflab-background-job.php';
	}

	public function test_class_constants_exists() {
		$this->job = new Perflab_Background_Job();
		$job_class = get_class( $this->job );

		$this->assertTrue( defined( $job_class . '::META_KEY_JOB_NAME' ) );
		$this->assertTrue( defined( $job_class . '::META_KEY_JOB_DATA' ) );
		$this->assertTrue( defined( $job_class . '::META_KEY_JOB_ATTEMPTS' ) );
		$this->assertTrue( defined( $job_class . '::META_KEY_JOB_LOCK' ) );
		$this->assertTrue( defined( $job_class . '::META_KEY_JOB_ERRORS' ) );
		$this->assertTrue( defined( $job_class . '::META_KEY_JOB_STATUS' ) );
		$this->assertTrue( defined( $job_class . '::JOB_STATUS_QUEUED' ) );
		$this->assertTrue( defined( $job_class . '::JOB_STATUS_RUNNING' ) );
		$this->assertTrue( defined( $job_class . '::JOB_STATUS_PARTIAL' ) );
		$this->assertTrue( defined( $job_class . '::JOB_STATUS_COMPLETE' ) );
		$this->assertTrue( defined( $job_class . '::JOB_STATUS_FAILED' ) );
	}

	public function test_class_constants_values() {
		$this->job = new Perflab_Background_Job();
		$job_class = get_class( $this->job );

		$this->assertEquals( 'perflab_job_name', constant( $job_class . '::META_KEY_JOB_NAME' ) );
		$this->assertEquals( 'perflab_job_data', constant( $job_class . '::META_KEY_JOB_DATA' ) );
		$this->assertEquals( 'perflab_job_attempts', constant( $job_class . '::META_KEY_JOB_ATTEMPTS' ) );
		$this->assertEquals( 'perflab_job_lock', constant( $job_class . '::META_KEY_JOB_LOCK' ) );
		$this->assertEquals( 'perflab_job_errors', constant( $job_class . '::META_KEY_JOB_ERRORS' ) );
		$this->assertEquals( 'perflab_job_status', constant( $job_class . '::META_KEY_JOB_STATUS' ) );
		$this->assertEquals( 'perflab_job_queued', constant( $job_class . '::JOB_STATUS_QUEUED' ) );
		$this->assertEquals( 'perflab_job_running', constant( $job_class . '::JOB_STATUS_RUNNING' ) );
		$this->assertEquals( 'perflab_job_partial', constant( $job_class . '::JOB_STATUS_PARTIAL' ) );
		$this->assertEquals( 'perflab_job_complete', constant( $job_class . '::JOB_STATUS_COMPLETE' ) );
		$this->assertEquals( 'perflab_job_failed', constant( $job_class . '::JOB_STATUS_FAILED' ) );
	}

	public function test_job_id_is_zero() {
		$this->job = new Perflab_Background_Job();

		$this->assertSame( 0, $this->job->job_id );
	}

	/**
	 * @covers ::set_status
	 */
	public function test_set_status_false_for_invalid_status() {
		$this->job = new Perflab_Background_Job();

		$result = $this->job->set_status( 'invalid_status' );
		$this->assertFalse( $result );
	}

	/**
	 * @covers ::set_status
	 */
	public function test_set_status_false_for_valid_status() {
		$this->job = Perflab_Background_Job::create( 'test' );

		$running_status  = $this->job->set_status( 'perflab_job_running' );
		$partial_status  = $this->job->set_status( 'perflab_job_partial' );
		$failed_status   = $this->job->set_status( 'perflab_job_failed' );
		$complete_status = $this->job->set_status( 'perflab_job_complete' );

		$this->assertTrue( $running_status );
		$this->assertTrue( $partial_status );
		$this->assertTrue( $failed_status );
		$this->assertTrue( $complete_status );
	}

	/**
	 * @covers ::create
	 */
	public function test_create_job() {
		$job_data = array(
			'post_id'          => 10,
			'some_random_data' => 'some_random_string',
		);

		$job = Perflab_Background_Job::create( 'test_job', $job_data );

		$this->assertInstanceOf( Perflab_Background_Job::class, $job );
	}

	/**
	 * @covers ::set_error
	 * @covers ::get_attempts
	 */
	public function test_set_error() {
		$error      = new WP_Error();
		$error_data = array(
			'test_error_data' => 'descriptive_infomation',
		);
		$error->add_data( $error_data, 'perflab_job_failure' );
		$this->job = new Perflab_Background_Job();
		$job_data  = array(
			'post_id'          => 10,
			'some_random_data' => 'some_random_string',
		);

		$this->job = Perflab_Background_Job::create( 'random', $job_data );
		$this->job->set_error( $error );

		$error_metadata = get_term_meta( $this->job->job_id, 'perflab_job_errors', true );
		$attempts       = $this->job->get_attempts();

		$this->assertSame( $error_data, $error_metadata );
		$this->assertEquals( 1, $attempts );
	}

	/**
	 * @covers ::should_run
	 */
	public function test_job_should_not_run_non_existing_job() {
		$this->job = new Perflab_Background_Job();

		$run = $this->job->should_run();
		$this->assertFalse( $run );
	}

	/**
	 * @covers ::should_run
	 */
	public function test_job_should_not_run_for_completed_job() {
		$this->job = new Perflab_Background_Job();
		$this->job->create( 'test_job' );

		// Mark job as complete.
		$this->job->set_status( 'perflab_job_complete' );
		$run = $this->job->should_run();

		$this->assertFalse( $run );
	}

	/**
	 * @covers ::lock
	 * @covers ::unlock
	 */
	public function test_lock_unlock() {
		$this->job = Perflab_Background_Job::create( 'test' );
		$time      = time();
		$this->job->lock( $time );
		$lock_time = get_term_meta( $this->job->job_id, 'perflab_job_lock', true );
		$this->assertSame( absint( $lock_time ), $time );
		$this->job->unlock();
		$lock_time = get_term_meta( $this->job->job_id, 'perflab_job_lock', true );
		$this->assertEmpty( $lock_time );
	}

	/**
	 * @covers ::get_start_time
	 */
	public function test_get_start_time() {
		$this->job = Perflab_Background_Job::create( 'test' );
		$time      = time();
		$this->job->lock( $time );
		$status    = $this->job->get_status();
		$lock_time = get_term_meta( $this->job->job_id, 'perflab_job_lock', true );
		$this->assertSame( absint( $lock_time ), $time );

		$start_time = $this->job->get_start_time();
		$this->assertEquals( $start_time, $lock_time );
		$this->assertEquals( $start_time, $time );
		$this->assertEquals( $time, $lock_time );
	}
}
