<?php
/**
 * Background process runner class.
 *
 * @since n.e.x.t
 * @package performance-lab
 */

/**
 * Class Perflab_Background_Process.
 *
 * Runs the heavy lifting tasks in background in separate process.
 */
class Perflab_Background_Process {
	/**
	 * Name of the action which will trigger background
	 * process to run the job.
	 *
	 * @const string
	 */
	const BG_PROCESS_ACTION = 'background_process_handle_request';

	/**
	 * Job instance.
	 *
	 * @var Perflab_Background_Job
	 */
	private $job;

	/**
	 * Perflab_Background_Process constructor.
	 *
	 * Adds the necessary hooks to trigger process handling.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'wp_ajax_' . Perflab_Background_Process::BG_PROCESS_ACTION, array( $this, 'handle_request' ) );
		add_action( 'wp_ajax_nopriv' . Perflab_Background_Process::BG_PROCESS_ACTION, array( $this, 'handle_request' ) );
	}

	/**
	 * Handle incoming request to run the batch of job.
	 *
	 * @throws Exception Invalid request for background process.
	 *
	 * @return void
	 */
	public function handle_request() {
		try {

			check_ajax_referer( Perflab_Background_Process::BG_PROCESS_ACTION, 'nonce' );

			$job_id = isset( $_POST['job_id'] ) ? absint( sanitize_text_field( $_POST['job_id'] ) ) : 0;

			// Job ID is mandatory to be specified.
			if ( empty( $job_id ) ) {
				throw new Exception( 'empty_background_job_id', __( 'No job specified to execute.', 'performance-lab' ) );
			}

			$this->job = new Perflab_Background_Job( $job_id );

			// Checks if job is valid or not.
			if ( ! $this->job->exists() || $this->job->completed() ) {
				throw new Exception( 'invalid_background_job', __( 'This job may have been completed already or does not exist.', 'performance-lab' ) );
			}

			// Silently exit if the job is still running.
			if ( $this->job->is_running() ) {
				return;
			}

			$this->lock();

			// If everything seems fine, attempt to run the job.

			$this->unlock();

		} catch ( Exception $e ) {
			$error = new WP_Error( 'background_job_failed', $e->getMessage() );
			$this->record_error( $error );
			$this->unlock();
		}
	}

	/**
	 * Records the error for the current process run.
	 *
	 * @param WP_Error $error Error instance.
	 *
	 * @todo Implement the error recording activity.
	 *
	 * @return void
	 */
	public function record_error( WP_Error $error ) {
		$this->job->set_status( 'failed' );
	}

	/**
	 * Checks whether the memory is exceeded for the current process.
	 *
	 * @return bool
	 */
	private function memory_exceeded() {
		return false;
	}

	/**
	 * Checks if current execution time is exceeded for the process.
	 *
	 * @return bool
	 */
	private function time_exceeded() {
		return false;
	}

	/**
	 * Locks the process. It tells that process is running.
	 *
	 * @return void
	 */
	private function lock() {
		$time = microtime();

		update_term_meta( $this->job->job_id, 'job_lock', $time );
		$this->job->set_status( 'running' );
	}

	/**
	 * Unlocks the process. It tells that process is running.
	 *
	 * @return void
	 */
	private function unlock() {
		delete_term_meta( $this->job->job_id, 'job_lock' );
	}
}
