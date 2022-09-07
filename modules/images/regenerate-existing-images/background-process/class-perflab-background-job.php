<?php
/**
 * Background job class.
 *
 * @since n.e.x.t
 * @package performance-lab
 */

/**
 * Class Perflab_Background_Job.
 *
 * Manage and run the background jobs.
 *
 * @since n.e.x.t
 */
class Perflab_Background_Job {
	/**
	 * Meta key for storing job name/action.
	 *
	 * @since n.e.x.t
	 * @var string Job name meta key.
	 */
	const JOB_NAME_META_KEY = 'perflab_job_name';

	/**
	 * Meta key for storing job data.
	 *
	 * @since n.e.x.t
	 * @var string Job data meta key.
	 */
	const JOB_DATA_META_KEY = 'perflab_job_data';

	/**
	 * Meta key for storing job data.
	 *
	 * @since n.e.x.t
	 * @var string Job attempts meta key.
	 */
	const JOB_ATTEMPTS_META_KEY = 'perflab_job_attempts';

	/**
	 * Meta key for storing job lock.
	 *
	 * @since n.e.x.t
	 * @var string Job lock meta key.
	 */
	const JOB_LOCK_META_KEY = 'perflab_job_lock';

	/**
	 * Meta key for storing job errors.
	 *
	 * @since n.e.x.t
	 * @var string Job errors meta key.
	 */
	const JOB_ERRORS_META_KEY = 'perflab_job_errors';

	/**
	 * Job status meta key.
	 *
	 * @since n.e.x.t
	 * @var string Job status meta key.
	 */
	const JOB_STATUS_META_KEY = 'perflab_job_status';

	/**
	 * Job status for queued jobs.
	 *
	 * @since n.e.x.t
	 * @var string Job status queued.
	 */
	const JOB_STATUS_QUEUED = 'perflab_job_queued';

	/**
	 * Job status for running jobs.
	 *
	 * @since n.e.x.t
	 * @var string Job status running.
	 */
	const JOB_STATUS_RUNNING = 'perflab_job_running';

	/**
	 * Job status for partially executed jobs.
	 *
	 * @since n.e.x.t
	 * @var string Job status partial.
	 */
	const JOB_STATUS_PARTIAL = 'perflab_job_partial';

	/**
	 * Job status for completed jobs.
	 *
	 * @since n.e.x.t
	 * @var string Job status commplete.
	 */
	const JOB_STATUS_COMPLETE = 'perflab_job_complete';

	/**
	 * Job status for failed jobs.
	 *
	 * @since n.e.x.t
	 * @var string Job status failed.
	 */
	const JOB_STATUS_FAILED = 'perflab_job_failed';

	/**
	 * Job ID.
	 *
	 * @since n.e.x.t
	 * @var int
	 */
	public $job_id;

	/**
	 * Job name.
	 *
	 * @since n.e.x.t
	 * @var string Job name.
	 */
	private $name;

	/**
	 * Job data.
	 *
	 * @since n.e.x.t
	 * @var array Job data.
	 */
	private $data;

	/**
	 * Perflab_Background_Job constructor.
	 *
	 * @since n.e.x.t
	 *
	 * @param int $job_id job ID.
	 */
	public function __construct( $job_id = 0 ) {
		$this->job_id = absint( $job_id );

		if ( $this->exists() ) {
			$this->name = get_term_meta( $this->job_id, self::JOB_NAME_META_KEY, true );
			$this->data = get_term_meta( $this->job_id, self::JOB_DATA_META_KEY, true );
		}
	}

	/**
	 * Determines whether a job should run for current request or not.
	 *
	 * It determines by checking if job is already running or completed.
	 * It also checks if max number of attempts have been attempted for the job.
	 * For any such condition, it will return false, else true.
	 *
	 * @since n.e.x.t
	 *
	 * @return bool Flag to tell whether this job should run or not.
	 */
	public function should_run() {
		// If job doesn't exist or completed, return false.
		if ( ! $this->exists() || $this->is_completed() ) {
			return false;
		}

		if ( $this->is_running() ) {
			return false;
		}

		/**
		 * Number of attempts to try to run a job.
		 * Repeated attempts may be required to run a failed job. Default 3 attempts.
		 *
		 * @since n.e.x.t
		 *
		 * @param int $attempts Number of attempts allowed for a job to run.
		 */
		$max_attempts = apply_filters( 'perflab_job_max_attempts_allowed', 3 );

		// If number of attempts have been exhausted, return false.
		if ( $this->get_attempts() >= $max_attempts ) {
			return false;
		}

		return true;
	}

	/**
	 * Sets the status of the current job.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $status Status to set for current job.
	 *
	 * @return bool Returns true if status is updated, false otherwise.
	 */
	public function set_status( $status ) {
		$valid_statuses = array(
			self::JOB_STATUS_COMPLETE,
			self::JOB_STATUS_FAILED,
			self::JOB_STATUS_QUEUED,
			self::JOB_STATUS_RUNNING,
			self::JOB_STATUS_PARTIAL,
		);

		if ( in_array( $status, $valid_statuses, true ) ) {
			update_term_meta( $this->job_id, self::JOB_STATUS_META_KEY, $status );

			// If job is complete, set the timestamp at which it was completed.
			if ( self::JOB_STATUS_COMPLETE === $status ) {
				update_term_meta( $this->job_id, 'job_completed_at', time() );
			}

			return true;
		}

		return false;
	}

	/**
	 * Retrieves the job status from its meta information.
	 *
	 * @since n.e.x.t
	 *
	 * @return string Job status.
	 */
	public function get_status() {
		return (string) get_term_meta( $this->job_id, self::JOB_STATUS_META_KEY, true );
	}

	/**
	 * Retrieves the job data.
	 *
	 * @since n.e.x.t
	 *
	 * @return array|null Job data.
	 */
	public function get_data() {
		// If we have cached data, return it.
		if ( ! is_null( $this->data ) ) {
			return $this->data;
		}

		$this->data = get_term_meta( $this->job_id, self::JOB_DATA_META_KEY, true );

		return $this->data;
	}

	/**
	 * Retrieves the job name.
	 *
	 * @since n.e.x.t
	 *
	 * @return string Job name.
	 */
	public function get_name() {
		return (string) $this->name;
	}

	/**
	 * Creates a new job in the queue.
	 *
	 * Job refers to the term and queue refers to the `background_job` taxonomy.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $name Name of job identifier.
	 * @param array  $data Data for the job.
	 *
	 * @return Perflab_Background_Job|WP_Error Job object if created successfully, else WP_Error.
	 */
	public static function create( $name, array $data = array() ) {
		// Insert the new job in queue.
		$term_data = wp_insert_term( 'job_' . time(), 'background_job' );

		if ( ! is_wp_error( $term_data ) ) {
			update_term_meta( $term_data['term_id'], self::JOB_DATA_META_KEY, $data );
			update_term_meta( $term_data['term_id'], self::JOB_NAME_META_KEY, $name );

			/**
			 * Create a fresh instance to return.
			 *
			 * @var Perflab_Background_Job
			 */
			$job = new self( $term_data['term_id'] );

			$job->name = $name;
			$job->data = $data;

			// Set the queued status for freshly created jobs.
			$job->set_status( self::JOB_STATUS_QUEUED );

			return $job;
		}

		return $term_data;
	}

	/**
	 * Get the batch for current run.
	 *
	 * This will return the items to process in the current batch.
	 *
	 * @since n.e.x.t
	 *
	 * @return array List of batch items.
	 */
	public function batch() {
		$items = array();

		/**
		 * Filters the items for the current batch of job.
		 *
		 * By default this will be empty array. Consumer code will need to use this filter in
		 * order to return the list of items which needs to be processed in current batch.
		 *
		 * @since n.e.x.t
		 *
		 * @param array  $items  Items for the current batch.
		 * @param int    $job_id Background job ID.
		 * @param string $name   Job identifier or name.
		 * @param array  $data   Job data.
		 */
		$items = apply_filters( 'perflab_job_batch_items', $items, $this->job_id, $this->name, $this->data );

		return (array) $items;
	}

	/**
	 * Process the batch item.
	 *
	 * @since n.e.x.t
	 *
	 * @param mixed $item Batch item for the job.
	 *
	 * @return void
	 */
	public function process( $item ) {
		/**
		 * Hook to this action to run the job.
		 *
		 * Concrete implementations would add the logic to run this job.
		 *
		 * @since n.e.x.t
		 *
		 * @param mixed $item   Batch item for the job.
		 * @param int   $job_id Job ID.
		 * @param array $data   Job data.
		 */
		do_action( 'perflab_process_' . $this->name . '_job_item', $item, $this->job_id, $this->data );
	}

	/**
	 * Returns the number of attempts executed for a job.
	 *
	 * @since n.e.x.t
	 *
	 * @return int
	 */
	public function get_attempts() {
		$attempts = get_term_meta( $this->job_id, self::JOB_ATTEMPTS_META_KEY, true );

		return absint( $attempts );
	}

	/**
	 * Set the start time of job.
	 * It tells at what point of time the job has been started.
	 *
	 * @since n.e.x.t
	 *
	 * @param int $time Timestamp (in seconds) at which job has been started.
	 *
	 * @return void
	 */
	public function lock( $time = null ) {
		$time = empty( $time ) ? time() : $time;
		update_term_meta( $this->job_id, self::JOB_LOCK_META_KEY, $time );
		$this->set_status( self::JOB_STATUS_RUNNING );
	}

	/**
	 * Get the timestamp (in seconds) when the job was started.
	 *
	 * @since n.e.x.t
	 *
	 * @return int
	 */
	public function get_start_time() {
		$time = get_term_meta( $this->job_id, self::JOB_LOCK_META_KEY, true );

		return absint( $time );
	}

	/**
	 * Unlocks the process.
	 *
	 * @since n.e.x.t
	 *
	 * @return void
	 */
	public function unlock() {
		delete_term_meta( $this->job_id, self::JOB_LOCK_META_KEY );
	}

	/**
	 * Records the error for the current process run.
	 *
	 * @since n.e.x.t
	 *
	 * @param WP_Error $error Error instance.
	 *
	 * @todo Implement the error recording activity.
	 *
	 * @return void
	 */
	public function record_error( WP_Error $error ) {
		$job_failure_data = $error->get_error_data( 'perflab_job_failure' );

		if ( ! empty( $job_failure_data ) ) {
			$this->set_status( self::JOB_STATUS_FAILED );

			update_term_meta( $this->job_id, self::JOB_ERRORS_META_KEY, $job_failure_data );
			update_term_meta( $this->job_id, self::JOB_ATTEMPTS_META_KEY, ( $this->get_attempts() + 1 ) );
		}
	}

	/**
	 * Checks that the job term exist.
	 *
	 * @since n.e.x.t
	 *
	 * @return array|null
	 */
	private function exists() {
		// @todo Replace taxonomy name with constant.
		return term_exists( $this->job_id, 'background_job' );
	}

	/**
	 * Checks if the job is running.
	 *
	 * If the job lock is present, it means job is running.
	 *
	 * @since n.e.x.t
	 *
	 * @return bool
	 */
	private function is_running() {
		return ( self::JOB_STATUS_RUNNING === $this->get_status() );
	}

	/**
	 * Checks if the background job is completed.
	 *
	 * @since n.e.x.t
	 *
	 * @return bool
	 */
	private function is_completed() {
		return ( self::JOB_STATUS_COMPLETE === $this->get_status() );
	}
}
