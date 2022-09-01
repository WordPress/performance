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

	public function test_class_constants_exists() {
		$this->job = new Perflab_Background_Job();

		$this->assertTrue( defined( get_class( $this->job ) . '::JOB_NAME_META_KEY' ) );
		$this->assertTrue( defined( get_class( $this->job ) . '::JOB_DATA_META_KEY' ) );
		$this->assertTrue( defined( get_class( $this->job ) . '::JOB_RETRY_META_KEY' ) );
		$this->assertTrue( defined( get_class( $this->job ) . '::JOB_LOCK_META_KEY' ) );
		$this->assertTrue( defined( get_class( $this->job ) . '::JOB_ERRORS_META_KEY' ) );
		$this->assertTrue( defined( get_class( $this->job ) . '::JOB_STATUS_META_KEY' ) );
		$this->assertTrue( defined( get_class( $this->job ) . '::JOB_STATUS_QUEUED' ) );
		$this->assertTrue( defined( get_class( $this->job ) . '::JOB_STATUS_RUNNING' ) );
		$this->assertTrue( defined( get_class( $this->job ) . '::JOB_STATUS_COMPLETE' ) );
		$this->assertTrue( defined( get_class( $this->job ) . '::JOB_STATUS_FAILED' ) );
	}

	public function test_class_constants_values() {
		$this->job = new Perflab_Background_Job();

		$this->assertEquals( 'perflab_job_name', constant( get_class( $this->job ) . '::JOB_NAME_META_KEY' ) );
		$this->assertEquals( 'perflab_job_data', constant( get_class( $this->job ) . '::JOB_DATA_META_KEY' ) );
		$this->assertEquals( 'perflab_job_retries', constant( get_class( $this->job ) . '::JOB_RETRY_META_KEY' ) );
		$this->assertEquals( 'perflab_job_lock', constant( get_class( $this->job ) . '::JOB_LOCK_META_KEY' ) );
		$this->assertEquals( 'perflab_job_errors', constant( get_class( $this->job ) . '::JOB_ERRORS_META_KEY' ) );
		$this->assertEquals( 'perflab_job_status', constant( get_class( $this->job ) . '::JOB_STATUS_META_KEY' ) );
		$this->assertEquals( 'perflab_job_queued', constant( get_class( $this->job ) . '::JOB_STATUS_QUEUED' ) );
		$this->assertEquals( 'perflab_job_running', constant( get_class( $this->job ) . '::JOB_STATUS_RUNNING' ) );
		$this->assertEquals( 'perflab_job_complete', constant( get_class( $this->job ) . '::JOB_STATUS_COMPLETE' ) );
		$this->assertEquals( 'perflab_job_failed', constant( get_class( $this->job ) . '::JOB_STATUS_FAILED' ) );
	}
}
