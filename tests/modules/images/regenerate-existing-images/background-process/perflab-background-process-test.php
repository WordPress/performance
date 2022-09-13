<?php
/**
 * Tests for background-process runner class.
 *
 * @package performance-lab
 * @group   background-process
 */

/**
 * Class Perflab_Background_Process_Test
 *
 * @coversDefaultClass Perflab_Background_Process
 * @group background-process
 */
class Perflab_Background_Process_Test extends WP_UnitTestCase {
	/**
	 * Process instance.
	 *
	 * @since n.e.x.t
	 *
	 * @var Perflab_Background_Process
	 */
	private $process;

	/**
	 * Runs before any test is executed inside class.
	 *
	 * @return void
	 */
	public static function set_up_before_class() {
		require_once PERFLAB_PLUGIN_DIR_PATH . 'modules/images/regenerate-existing-images/background-process/class-perflab-background-job.php';
		require_once PERFLAB_PLUGIN_DIR_PATH . 'modules/images/regenerate-existing-images/background-process/class-perflab-background-process.php';
	}

	/**
	 * Test that constant exists and its value.
	 *
	 * @since n.e.x.t
	 *
	 * @covers ::__construct
	 */
	public function test_class_constants_exists() {
		$this->process = new Perflab_Background_Process();
		$process_class = get_class( $this->process );
		$this->assertTrue( defined( $process_class . '::BG_PROCESS_ACTION' ) );
		$constant_value = constant( $process_class . '::BG_PROCESS_ACTION' );
		$this->assertEquals( 'perflab_background_process_handle_request', $constant_value );
	}

	/**
	 * Test that ajax hooks are getting added when object is instantiated.
	 *
	 * @since n.e.x.t
	 *
	 * @covers ::__construct
	 */
	public function test_handle_request_actions_added() {
		$this->process  = new Perflab_Background_Process();
		$process_class  = get_class( $this->process );
		$constant_value = constant( $process_class . '::BG_PROCESS_ACTION' );

		$authenticated_action     = has_action( 'wp_ajax_' . $constant_value, array( $this->process, 'handle_request' ) );
		$non_authenticated_action = has_action( 'wp_ajax_nopriv_' . $constant_value, array( $this->process, 'handle_request' ) );

		// Ensure has_action is not returning false.
		$this->assertNotEquals( false, $authenticated_action );
		$this->assertNotEquals( false, $non_authenticated_action );

		// has_action will return priority, so check that as well.
		$this->assertEquals( 10, $authenticated_action );
		$this->assertEquals( 10, $non_authenticated_action );
	}

	/**
	 * Tests that exception is thrown when job_id is not passed.
	 *
	 * @since n.e.x.t
	 *
	 * @covers ::handle_request
	 */
	public function test_handle_request() {
		$this->process  = new Perflab_Background_Process();
		$process_class  = get_class( $this->process );
		$constant_value = constant( $process_class . '::BG_PROCESS_ACTION' );
		$nonce          = wp_create_nonce( $constant_value );
		$job            = perflab_create_background_job( 'test_task' );

		// Prepare request params.
		$_REQUEST['nonce']  = $nonce;
		$_REQUEST['job_id'] = $job->get_id();
		$this->process->handle_request();
		$hook_ran = did_action( 'perflab_job_test_task' );
		$hook_ran = 1 < $hook_ran;

		$this->assertTrue( $hook_ran );
	}
}
