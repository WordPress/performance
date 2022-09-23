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
 *
 * @since n.e.x.t
 */
class Perflab_Background_Process {
	/**
	 * Name of the action which will trigger background
	 * process to run the job.
	 *
	 * @since n.e.x.t
	 *
	 * @var string
	 */
	const BG_PROCESS_ACTION = 'perflab_background_process_handle_request';

	/**
	 * Job instance.
	 *
	 * @since n.e.x.t
	 *
	 * @var Perflab_Background_Job|null
	 */
	private $job;

	/**
	 * Perflab_Background_Process constructor.
	 *
	 * Adds the necessary hooks to trigger process handling.
	 *
	 * @since n.e.x.t
	 */
	public function __construct() {
		// Handle job execution request.
		add_action( 'wp_ajax_' . Perflab_Background_Process::BG_PROCESS_ACTION, array( $this, 'handle_request' ) );
		add_action( 'wp_ajax_nopriv_' . Perflab_Background_Process::BG_PROCESS_ACTION, array( $this, 'handle_request' ) );

		// Handle status check cron.
		add_action( 'perflab_background_process_status_check', array( $this, 'status_check' ) );
	}

	/**
	 * Handle incoming request to run the batch of job.
	 *
	 * @since n.e.x.t
	 *
	 * @throws Exception Invalid request for background process.
	 */
	public function handle_request() {
		try {
			// Exit if nonce varification fails.
			$nonce_check = check_ajax_referer( self::BG_PROCESS_ACTION, 'nonce', false );

			// If nonce check fails, fallback to key checking.
			if ( false === $nonce_check ) {
				throw new Exception( __( 'Invalid nonce passed to process.', 'performance-lab' ) );
			}

			$job_id = isset( $_REQUEST['job_id'] ) ? absint( sanitize_text_field( $_REQUEST['job_id'] ) ) : 0;

			// Job ID is mandatory to be specified.
			if ( empty( $job_id ) ) {
				throw new Exception( __( 'No job specified to execute.', 'performance-lab' ) );
			}

			$this->job = new Perflab_Background_Job( $job_id );

			// Silently exit if the job is not ready to run.
			if ( ! $this->job->should_run() ) {
				return;
			}

			$this->run(); // Run the job.

		} catch ( Exception $e ) {
			// @todo Add the error handling
		} finally {
			if ( $this->job instanceof Perflab_Background_Job ) {
				// Unlock the process once everything is done.
				$this->job->unlock();
				$this->next_batch( $this->job->get_id() );
			}
		}
	}

	/**
	 * Status check cron handler.
	 *
	 * @since n.e.x.t
	 *
	 * 1. Cron will be schedule if it does not exist already.
	 * 2. It will restart any job which is halted due to failure.
	 * 3. Remove old completed jobs.
	 */
	public function status_check() {
		$this->status_check_running_jobs();
		$this->delete_completed_jobs();
	}

	/**
	 * Check the running jobs.
	 *
	 * If they are still running, bail it.
	 * If the execution has been stopped by exceeding maximum execution
	 * time, restart the job.
	 *
	 * @since n.e.x.t
	 */
	private function status_check_running_jobs() {
		$jobs_args = array(
			'taxonomy'   => PERFLAB_BACKGROUND_JOB_TAXONOMY_SLUG,
			'meta_key'   => Perflab_Background_Job::META_KEY_JOB_STATUS,
			'meta_value' => Perflab_Background_Job::JOB_STATUS_RUNNING,
			'fields'     => 'ids',
		);

		// Fetch all the running jobs.
		$running_jobs = get_terms( $jobs_args );

		if ( ! empty( $running_jobs ) && ! is_wp_error( $running_jobs ) ) {
			foreach ( $running_jobs as $running_job ) {
				$job_lock = get_term_meta( $running_job, Perflab_Background_Job::META_KEY_JOB_LOCK, true );
				$job_lock = absint( $job_lock );

				// If job lock is not present, trigger the job again.
				if ( empty( $job_lock ) || $this->time_exceeded( $running_job ) ) {
					perflab_dispatch_background_process_request( $running_job );
				}
			}
		}
	}

	/**
	 * Check the completed jobs.
	 *
	 * If the job has been marked as completed and completed
	 * time has been over 7 days, remove that job from job queue (history).
	 *
	 * @since n.e.x.t
	 */
	private function delete_completed_jobs() {
		$jobs_args = array(
			'taxonomy'   => PERFLAB_BACKGROUND_JOB_TAXONOMY_SLUG,
			'meta_key'   => Perflab_Background_Job::META_KEY_JOB_STATUS,
			'meta_value' => Perflab_Background_Job::JOB_STATUS_COMPLETE,
			'fields'     => 'ids',
		);

		$completed_jobs = get_terms( $jobs_args );

		if ( ! empty( $completed_jobs ) && ! is_wp_error( $completed_jobs ) ) {
			$expiry_days  = 7 * DAY_IN_SECONDS;
			$current_time = time();

			foreach ( $completed_jobs as $completed_job ) {
				$job_completed_at = get_term_meta( $completed_job, 'perflab_job_completed_at', true );
				$job_completed_at = absint( $job_completed_at );
				$valid_till       = $job_completed_at + $expiry_days;

				// If completed job has more than 7 days, delete/remove it.
				if ( $current_time > $valid_till ) {
					wp_delete_term( $completed_job, PERFLAB_BACKGROUND_JOB_TAXONOMY_SLUG );
				}
			}
		}
	}

	/**
	 * Runs the process over a batch of job.
	 *
	 * As of now, it won't fetch the next batch if memory or time
	 * is not exceeded, but this can be introduced later.
	 * Consumer code can fine-tune the batch size according to requirement.
	 *
	 * @since n.e.x.t
	 */
	private function run() {
		// Lock the process for this job before running.
		$this->job->lock();

		do {
			/**
			 * Consumer code will hook to this action to perform necessary tasks.
			 *
			 * @param array $data Job data.
			 */
			do_action( 'perflab_job_' . $this->job->get_identifier(), $this->job->get_data() );
		} while ( ! $this->memory_exceeded() && ! $this->time_exceeded( $this->job->get_id() ) && ! $this->job->is_completed() );
	}

	/**
	 * Call the next batch of the job if it is not completed already.
	 *
	 * This will send a POST request to admin-ajax.php with background
	 * process specific action to continue executing the job in a new process.
	 *
	 * @since n.e.x.t
	 *
	 * @param int $job_id Job ID. This is the term id from `background_job` taxonomy.
	 * @return void
	 */
	private function next_batch( $job_id ) {
		/**
		 * Do not call the background process from within the script if the
		 * real cron has been setup to do so.
		 */
		if ( defined( 'ENABLE_BG_PROCESS_CRON' ) ) {
			return;
		}

		perflab_dispatch_background_process_request( $job_id );
	}

	/**
	 * Checks whether the memory is exceeded for the current process.
	 *
	 * Keep memory limit to 90% of the values assigned to PHP config,
	 * it will prevent the error and help run the memory exhaustion check.
	 *
	 * @since n.e.x.t
	 *
	 * @return bool Whether the memory has been exceeded for currently running script.
	 */
	private function memory_exceeded() {
		$memory_limit     = $this->get_memory_limit() * 0.9; // Use 90% of memory limit.
		$memmory_usage    = memory_get_usage( true ); // Memory allotted to php.
		$memory_exhausted = false;

		// Check if memory usage has reached the memory limit (90%).
		if ( $memmory_usage >= $memory_limit ) {
			$memory_exhausted = true;
		}

		/**
		 * Filters the memory exceeded flag.
		 *
		 * @since n.e.x.t
		 *
		 * @param bool $memory_exhausted Whether the memory limit has been reached.
		 */
		return apply_filters( 'perflab_background_process_memory_exceeded', $memory_exhausted );
	}

	/**
	 * Get the memory limit allotted for PHP.
	 *
	 * Keep the default memory limit of 128M which
	 * is there on most of the hosts.
	 *
	 * @since n.e.x.t
	 *
	 * @return int Memory limit allotted for script execution.
	 */
	private function get_memory_limit() {
		$default_memory_limit = '128M';

		if ( function_exists( 'memory_limit' ) ) {
			$memory_limit = ini_get( 'memory_limit' );
		}

		if ( empty( $memory_limit ) || -1 === $memory_limit ) {
			$memory_limit = $default_memory_limit;
		}

		// Convert memory to bytes in integer format.
		return wp_convert_hr_to_bytes( $memory_limit );
	}

	/**
	 * Checks if current execution time is exceeded for the process.
	 *
	 * A sensible default of 20 seconds has been taken in case ini_get does not
	 * work. Keep max_execution_time to 10 seconds less than whatever is set in PHP
	 * configuration, so that those 10 seconds can be used to cleanup everything and
	 * call the next batch of job.
	 *
	 * @since n.e.x.t
	 *
	 * @param int $job_id Job ID. Term ID for `background_job` taxonomy.
	 * @return bool Whether the time has been exceeded for currently running script.
	 */
	private function time_exceeded( $job_id ) {
		$job                = new Perflab_Background_Job( $job_id );
		$current_time       = time();
		$run_start_time     = $job->get_start_time();
		$max_execution_time = $this->get_max_execution_time();

		$time_exceeded = ( $current_time >= ( $run_start_time + $max_execution_time ) );

		/**
		 * Whether the time to allotted for PHP has been exceeded.
		 *
		 * @since n.e.x.t
		 *
		 * @param bool $time_exceeded Time exceeded flag.
		 */
		return apply_filters( 'perflab_background_process_time_exceeded', $time_exceeded );
	}

	/**
	 * Get the maximum execution time for php script.
	 *
	 * By default this will be 20 seconds.
	 * If init_get returns the time, max execution time will be 10 seconds less than
	 * what has been returned. These 10 seconds will be utilised to perform cleanup actions
	 * like unlocking the job and making new request for background process.
	 *
	 * @since n.e.x.t
	 *
	 * @return int Time (in seconds ) for execution of the currently running script.
	 */
	private function get_max_execution_time() {
		$min_execution_time = 20; // Default to 20 seconds. Almost, all servers will have this much of time.
		$max_execution_time = $min_execution_time;

		if ( function_exists( 'ini_get' ) ) {
			$time               = ini_get( 'max_execution_time' );
			$max_execution_time = ( ! empty( $time ) && ( $time > $min_execution_time ) ) ? $time - 10 : $min_execution_time;
		}

		return $max_execution_time;
	}
}
