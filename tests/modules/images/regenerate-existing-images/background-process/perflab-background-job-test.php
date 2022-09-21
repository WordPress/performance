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
		$this->job = perflab_create_background_job( 'test' );

		$result = $this->job->set_status( 'invalid_status' );
		$this->assertFalse( $result );
	}

	/**
	 * @covers ::set_status
	 */
	public function test_set_status_false_for_valid_status() {
		$this->job = perflab_create_background_job( 'test' );

		$running_status  = $this->job->set_status( 'running' );
		$partial_status  = $this->job->set_status( 'partial' );
		$failed_status   = $this->job->set_status( 'failed' );
		$complete_status = $this->job->set_status( 'completed' );

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

		$job = perflab_create_background_job( 'test_job', $job_data );

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
		$job_data = array(
			'post_id'          => 10,
			'some_random_data' => 'some_random_string',
		);

		$this->job = perflab_create_background_job( 'tecdcdcdst', $job_data );
		$this->job->set_error( $error );

		$error_metadata = get_term_meta( $this->job->get_id(), 'perflab_job_errors', true );
		$attempts       = $this->job->get_attempts();

		$this->assertSame( $error_data, $error_metadata );
		$this->assertEquals( 1, $attempts );
	}

	/**
	 * @covers ::should_run
	 */
	public function test_job_should_run_for_job() {
		$this->job = perflab_create_background_job( 'test' );

		$run = $this->job->should_run();
		$this->assertTrue( $run );
	}

	/**
	 * @covers ::should_run
	 */
	public function test_job_should_not_run_for_completed_job() {
		$this->job = perflab_create_background_job( 'test' );

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
		$this->job = perflab_create_background_job( 'test' );
		$time      = time();
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
		$this->job = perflab_create_background_job( 'test' );
		$time      = time();
		$this->job->lock( $time );
		$lock_time = get_term_meta( $this->job->get_id(), 'perflab_job_lock', true );
		$this->assertSame( absint( $lock_time ), $time );

		$start_time = $this->job->get_start_time();
		$this->assertEquals( $start_time, $lock_time );
		$this->assertEquals( $start_time, $time );
		$this->assertEquals( $time, $lock_time );
	}

	public function test_max_attempts_filter() {
		$job = perflab_create_background_job( 'test' );
		add_filter( 'perflab_job_max_attempts_allowed', array( $this, 'max_attempts_10' ) );
		$job->should_run();
		remove_filter( 'perflab_job_max_attempts_allowed', array( $this, 'max_attempts_10' ) );
		$filter_ran = property_exists( $this, 'attempt_filter_ran' );

		$this->assertTrue( $filter_ran );
	}

	public function test_max_attempts_limit_reached() {
		$job                   = perflab_create_background_job( 'test' );
		$before_attempts_limit = $job->should_run();
		update_term_meta( $job->get_id(), $job::META_KEY_JOB_ATTEMPTS, 2 );
		add_filter( 'perflab_job_max_attempts_allowed', array( $this, 'max_attempts_1' ) );
		$after_attempts_limit = $job->should_run();
		remove_filter( 'perflab_job_max_attempts_allowed', array( $this, 'max_attempts_1' ) );

		$this->assertTrue( $before_attempts_limit );
		$this->assertFalse( $after_attempts_limit );
	}

	public function max_attempts_10() {
		$this->attempt_filter_ran = true;

		return 10;
	}

	public function max_attempts_1() {
		return 1;
	}

	/**
	 * Runs after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		if ( $this->job instanceof Perflab_Background_Job ) {
			wp_delete_term( $this->job->get_id(), 'background_job' );
		}
	}
}
