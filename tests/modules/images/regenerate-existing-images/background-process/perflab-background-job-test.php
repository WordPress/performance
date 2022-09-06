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

		$this->assertTrue( defined( $job_class . '::JOB_NAME_META_KEY' ) );
		$this->assertTrue( defined( $job_class . '::JOB_DATA_META_KEY' ) );
		$this->assertTrue( defined( $job_class . '::JOB_ATTEMPTS_META_KEY' ) );
		$this->assertTrue( defined( $job_class . '::JOB_LOCK_META_KEY' ) );
		$this->assertTrue( defined( $job_class . '::JOB_ERRORS_META_KEY' ) );
		$this->assertTrue( defined( $job_class . '::JOB_STATUS_META_KEY' ) );
		$this->assertTrue( defined( $job_class . '::JOB_STATUS_QUEUED' ) );
		$this->assertTrue( defined( $job_class . '::JOB_STATUS_RUNNING' ) );
		$this->assertTrue( defined( $job_class . '::JOB_STATUS_PARTIAL' ) );
		$this->assertTrue( defined( $job_class . '::JOB_STATUS_COMPLETE' ) );
		$this->assertTrue( defined( $job_class . '::JOB_STATUS_FAILED' ) );
	}

	public function test_class_constants_values() {
		$this->job = new Perflab_Background_Job();
		$job_class = get_class( $this->job );

		$this->assertEquals( 'perflab_job_name', constant( $job_class . '::JOB_NAME_META_KEY' ) );
		$this->assertEquals( 'perflab_job_data', constant( $job_class . '::JOB_DATA_META_KEY' ) );
		$this->assertEquals( 'perflab_job_attempts', constant( $job_class . '::JOB_ATTEMPTS_META_KEY' ) );
		$this->assertEquals( 'perflab_job_lock', constant( $job_class . '::JOB_LOCK_META_KEY' ) );
		$this->assertEquals( 'perflab_job_errors', constant( $job_class . '::JOB_ERRORS_META_KEY' ) );
		$this->assertEquals( 'perflab_job_status', constant( $job_class . '::JOB_STATUS_META_KEY' ) );
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

	public function test_set_status_false_for_invalid_status() {
		$this->job = new Perflab_Background_Job();

		$result = $this->job->set_status( 'invalid_status' );
		$this->assertFalse( $result );
	}

	/**
	 * @covers ::create
	 */
	public function test_create_job() {
		$this->job = new Perflab_Background_Job();

		$job_data = array(
			'post_id'          => 10,
			'some_random_data' => 'some_random_string',
		);

		$job_info = $this->job->create( 'test_job', $job_data );

		$this->assertIsArray( $job_info );
		$this->assertArrayHasKey( 'term_id', $job_info );
		$this->assertArrayHasKey( 'term_taxonomy_id', $job_info );

		$job_created_hook = did_action( 'perflab_job_created' );

		$this->assertEquals( 1, $job_created_hook );
	}

	/**
	 * Returns wp error for non-zero job id.
	 *
	 * @covers ::create
	 */
	public function test_create_returns_wp_error() {
		$this->job = new Perflab_Background_Job( 10 ); // non-zero ID.
		$job_data  = $this->job->create( 'test_job', array() );

		$this->assertInstanceOf( WP_Error::class, $job_data );
	}

	/**
	 * @covers ::batch
	 *
	 * @todo Add the check if filter ran, once 6.1 is released.
	 * @see https://core.trac.wordpress.org/ticket/35357
	 */
	public function test_batch() {
		$this->job = new Perflab_Background_Job();

		$items = $this->job->batch();

		$this->assertIsArray( $items );
		$this->assertEmpty( $items );
	}

	/**
	 * @covers ::process
	 */
	public function test_process() {
		$this->job = new Perflab_Background_Job();

		$job_data = array(
			'post_id'          => 10,
			'some_random_data' => 'some_random_string',
		);

		$job_info = $this->job->create( 'random', $job_data );

		$test_items = array(
			'test_item_1',
			'test_item_2',
			'test_item_3',
			'test_item_4',
			'test_item_5',
		);

		foreach ( $test_items as $item ) {
			$this->job->process( $item );
		}

		$process_hook = did_action( 'perflab_process_random_job_item' );

		$this->assertEquals( 5, $process_hook );
	}

	/**
	 * @covers ::record_error
	 * @covers ::get_attempts
	 */
	public function test_record_error() {
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
		$this->job->create( 'random', $job_data );
		$this->job->record_error( $error );

		$error_metadata = get_term_meta( $this->job->job_id, 'perflab_job_errors', true );
		$attempts       = $this->job->get_attempts();

		$this->assertSame( $error_data, $error_metadata );
		$this->assertEquals( 1, $attempts );
	}

	public function test_job_should_not_run_non_existing_job() {
		$this->job = new Perflab_Background_Job();

		$run = $this->job->should_run();
		$this->assertFalse( $run );
	}

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
	 * @covers ::get_status
	 */
	public function test_lock() {
		$this->job = new Perflab_Background_Job();
		$this->job->create( 'test_job' );

		$this->job->lock();
		$status = $this->job->get_status();

		$this->assertSame( 'perflab_job_running', $status );
	}
}
