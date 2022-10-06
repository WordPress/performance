<?php
/**
 * Tests for background-process runner class.
 *
 * @package performance-lab
 * @group   regenerate-existing-images
 */

/**
 * Class Perflab_Background_Process_Test
 *
 * @coversDefaultClass Perflab_Background_Process
 * @group regenerate-existing-images
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
	 * Runs before each test in class.
	 */
	public function set_up() {
		$this->process = new Perflab_Background_Process();
	}

	/**
	 * Test that constant exists and its value.
	 *
	 * @since n.e.x.t
	 *
	 * @covers ::__construct
	 */
	public function test_ajax_action_constant() {
		$this->assertEquals( 'perflab_background_process_handle_request', Perflab_Background_Process::BG_PROCESS_ACTION );
	}

	/**
	 * Test that ajax hooks are getting added when object is instantiated.
	 *
	 * @since n.e.x.t
	 *
	 * @covers ::__construct
	 */
	public function test_handle_request_actions_added() {
		$authenticated_action = has_action( 'wp_ajax_' . Perflab_Background_Process::BG_PROCESS_ACTION, array( $this->process, 'handle_request' ) );

		// Ensure has_action is not returning false.
		$this->assertNotEquals( false, $authenticated_action );

		// has_action will return priority, so check that as well.
		$this->assertEquals( 10, $authenticated_action );
	}

	/**
	 * Tests that exception is thrown when job_id is not passed.
	 *
	 * @since n.e.x.t
	 *
	 * @covers ::handle_request
	 */
	public function test_handle_request() {
		$nonce = wp_create_nonce( Perflab_Background_Process::BG_PROCESS_ACTION );
		$job   = perflab_create_background_job( 'test_task' );

		// Prepare request params.
		$_REQUEST['nonce']  = $nonce;
		$_REQUEST['job_id'] = $job->get_id();

		$this->process->handle_request();
		$hook_ran = did_action( 'perflab_job_test_task' );
		$hook_ran = 1 < $hook_ran;

		$this->assertTrue( $hook_ran );
	}
}
