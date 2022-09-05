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
	 * @since n.e.x.t
	 *
	 * @var string Background process action name.
	 */
	const BG_PROCESS_ACTION = 'background_process_handle_request';

	/**
	 * Job instance.
	 *
	 * @since n.e.x.t
	 *
	 * @var Perflab_Background_Job Background job instance.
	 */
	private $job;

	/**
	 * Perflab_Background_Process constructor.
	 *
	 * Adds the necessary hooks to trigger process handling.
	 *
	 * @since n.e.x.t
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
	 * @since n.e.x.t
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

			// Silently exit if the job is not ready to run.
			if ( $this->job->should_run() ) {
				return;
			}

			$this->job->lock(); // Lock the process for this job before running.
			$this->run(); // Run the job.

		} catch ( Exception $e ) {

			$error = new WP_Error( 'perflab_job_failure', $e->getMessage() );
			$this->job->record_error( $error );

		} finally {
			// Unlock the process once everything is done.
			$this->job->unlock();
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
	 * @since n.e.x.t
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
	 * Checks whether the memory is exceeded for the current process.
	 *
	 * @since n.e.x.t
	 *
	 * @return bool
	 */
	private function memory_exceeded() {
		return false;
	}

	/**
	 * Checks if current execution time is exceeded for the process.
	 *
	 * @since n.e.x.t
	 *
	 * @return bool
	 */
	private function time_exceeded() {
		$current_time       = time();
		$run_start_time     = $this->job->get_start_time();
		$min_execution_time = 20; // Default to 20 seconds. Almost, all servers will have this much of time.

		if ( function_exists( 'ini_get' ) ) {
			$time               = ini_get( 'max_execution_time' );
			$max_execution_time = ( ! empty( $time ) && ( $time > $min_execution_time ) ) ? $time - 10 : $min_execution_time;
		}

		return ( $current_time >= ( $run_start_time + $max_execution_time ) );
	}
}
