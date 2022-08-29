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

			// Exit if nonce varification fails.
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

			$this->lock(); // Lock the process for this job before running.
			$this->run(); // Run the job.

		} catch ( Exception $e ) {

			$error = new WP_Error( 'background_job_failed', $e->getMessage() );
			$this->record_error( $error );

		} finally {
			// Unlock the process once everything is done.
			$this->unlock();
		}
	}

	/**
	 * Runs the process over a batch of job.
	 *
	 * As of now, it won't fetch the next batch if memory or time
	 * is not exceeded, but this can be introduced later.
	 *
	 * Consumer code can fine-tune the batch size according to requirement.
	 *
	 * @return void
	 */
	private function run() {
		$iterator    = 0;
		$batch_items = array_values( $this->job->batch() ); // Ensure array is numerically indexed.

		if ( ! empty( $batch_items ) ) {
			do {
				$this->job->process( $batch_items[ $iterator ] );

				unset( $batch_items[ $iterator ] );
				// Increment the count.
				$iterator += 1;
			} while ( ! $this->memory_exceeded() && ! $this->time_exceeded() && ! empty( $batch_items ) );

			return;
		}

		// If we are here, means there are no batch items to process, so mark job as complete.
		$this->job->set_status( 'complete' );
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
		$current_time       = time();
		$run_start_time     = $this->job->is_running();
		$max_execution_time = 20; // Default to 20 seconds.

		if ( function_exists( 'ini_get' ) ) {
			$time               = ini_get( 'max_execution_time' );
			$max_execution_time = ( ! empty( $time ) && ( $time > 0 ) ) ? $time - 10 : $max_execution_time;
		}

		return ( $current_time >= ( $run_start_time + $max_execution_time ) );
	}

	/**
	 * Locks the process. It tells that process is running.
	 *
	 * @return void
	 */
	private function lock() {
		$time = time();

		update_term_meta( $this->job->job_id, 'job_lock', $time );
		$this->job->set_status( 'running' );
	}

	/**
	 * Unlocks the process.
	 *
	 * @return void
	 */
	private function unlock() {
		delete_term_meta( $this->job->job_id, 'job_lock' );
	}
}
