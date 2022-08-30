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
	 * @const string
	 * @since n.e.x.t
	 */
	const JOB_NAME_META_KEY = 'perflab_job_name';

	/**
	 * Meta key for storing job data.
	 *
	 * @const string
	 * @since n.e.x.t
	 */
	const JOB_DATA_META_KEY = 'perflab_job_data';

	/**
	 * Meta key for storing job data.
	 *
	 * @const string
	 * @since n.e.x.t
	 */
	const JOB_RETRY_META_KEY = 'perflab_job_retries';

	/**
	 * Meta key for storing job lock.
	 *
	 * @const string
	 * @since n.e.x.t
	 */
	const JOB_LOCK_META_KEY = 'perflab_job_lock';

	/**
	 * Meta key for storing job errors.
	 *
	 * @const string
	 * @since n.e.x.t
	 */
	const JOB_ERRORS_META_KEY = 'perflab_job_errors';

	/**
	 * Job status meta key.
	 *
	 * @const string
	 * @since n.e.x.t
	 */
	const JOB_STATUS_META_KEY = 'perflab_job_status';

	/**
	 * Job status for queued jobs.
	 *
	 * @const string
	 * @since n.e.x.t
	 */
	const JOB_STATUS_QUEUED = 'perflab_job_queued';

	/**
	 * Job status for running jobs.
	 *
	 * @const string
	 * @since n.e.x.t
	 */
	const JOB_STATUS_RUNNING = 'perflab_job_running';

	/**
	 * Job status for completed jobs.
	 *
	 * @const string
	 * @since n.e.x.t
	 */
	const JOB_STATUS_COMPLETE = 'perflab_job_complete';

	/**
	 * Job status for failed jobs.
	 *
	 * @const string
	 * @since n.e.x.t
	 */
	const JOB_STATUS_FAILED = 'perflab_job_failed';

	/**
	 * Job ID.
	 *
	 * @var int
	 */
	public $job_id;

	/**
	 * Job name.
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Job data.
	 *
	 * @var array
	 */
	public $data;

	/**
	 * Perflab_Background_Job constructor.
	 *
	 * @since n.e.x.t
	 *
	 * @param int $job_id job ID.
	 */
	public function __construct( $job_id = 0 ) {
		$this->job_id = absint( $job_id );

		// @todo Replace taxonomy name with constant.
		if ( term_exists( $this->job_id, 'background_job' ) ) {
			$this->name = get_term_meta( $this->job_id, self::JOB_NAME_META_KEY, true );
			$this->data = get_term_meta( $this->job_id, self::JOB_DATA_META_KEY, true );
		}
	}

	/**
	 * Checks that the job term exist.
	 *
	 * @since n.e.x.t
	 *
	 * @return array|null
	 */
	public function exists() {
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
	public function is_running() {
		return ( self::JOB_STATUS_RUNNING === $this->get_status() );
	}

	/**
	 * Checks if the background job is completed.
	 *
	 * @since n.e.x.t
	 *
	 * @return bool
	 */
	public function is_complete() {
		return ( self::JOB_STATUS_COMPLETE === $this->get_status() );
	}

	/**
	 * Determines whether a job should run for current request or not.
	 *
	 * It determines by checking if job is already running or completed.
	 * It also checks if max number of retries have been attempted for the job.
	 * For any such condition, it will return false, else true.
	 *
	 * @since n.e.x.t
	 *
	 * @return bool Flag to tell whether this job should run or not.
	 */
	public function should_run() {
		// If job doesn't exist or completed, return false.
		if ( ! $this->exists() || $this->is_complete() ) {
			return false;
		}

		// If number of attempts have been exhausted, return false.
		if ( $this->get_retries() >= $this->max_retries_allowed() ) {
			return false;
		}

		if ( $this->is_running() ) {
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
	 * @return string
	 */
	public function get_status() {
		return (string) get_term_meta( $this->job_id, self::JOB_STATUS_META_KEY, true );
	}

	/**
	 * Retrieves the job data.
	 *
	 * @since n.e.x.t
	 *
	 * @return array|null
	 */
	public function data() {
		/**
		 * If we have cached data, return it.
		 */
		if ( ! is_null( $this->data ) ) {
			return $this->data;
		}

		$this->data = get_term_meta( $this->job_id, self::JOB_DATA_META_KEY, true );

		return $this->data;
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
	 * @return int|WP_Error
	 */
	public function create( $name, array $data ) {
		// @todo Replace taxonomy name with constant.
		// Create job only when job ID for current instance is non-zero and term doesn't exist.
		if ( $this->job_id > 0 && term_exists( $this->job_id, 'background_job' ) ) {
			return new WP_Error( __( 'Cannot create the job as it exists already.', 'performance-lab' ) );
		}

		$term_name = 'job_' . microtime();

		// Insert the new job in queue.
		$term_data = wp_insert_term(
			$term_name,
			'background_job',
			array(
				'slug' => $term_name,
			)
		);

		if ( ! is_wp_error( $term_data ) ) {
			// Set object properties for instance as soon as job is created.
			$this->job_id = $term_data['term_id'];
			$this->name   = sanitize_text_field( $name );
			$this->data   = $data;

			// Set the queued status for freshly created jobs.
			$this->set_status( self::JOB_STATUS_QUEUED );

			update_term_meta( $this->job_id, self::JOB_DATA_META_KEY, $this->data );
			update_term_meta( $this->job_id, self::JOB_NAME_META_KEY, $this->name );

			/**
			 * Fires when the job has been created successfully.
			 *
			 * @since n.e.x.t
			 *
			 * @param int    $job_id Job ID.
			 * @param string $name Job name.
			 * @param array  $data Job data.
			 */
			do_action( 'perflab_job_created', $this->job_id, $this->name, $this->data );
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
	 * @return array
	 */
	public function batch() {
		$items = array();

		/**
		 * Filters the items for the current batch of job.
		 *
		 * By default this will be empty array. Consumer code will need to use this filter in
		 * order to return the list of items which needs to be processed in current batch.
		 *
		 * @param array  $items  Items for the current batch.
		 * @param int    $job_id Background job ID.
		 * @param string $name   Job identifier or name.
		 * @param array  $data   Job data.
		 *
		 * @since n.e.x.t
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
		 * @param mixed $item Batch item for the job.
		 * @param int   $job_id Job ID.
		 * @param array $data   Job data.
		 */
		do_action( 'perflab_process_' . $this->name . '_job_item', $item, $this->job_id, $this->data );
	}

	/**
	 * Get number of retries attempted for a job.
	 *
	 * @since n.e.x.t
	 *
	 * @return int
	 */
	public function get_retries() {
		$retries = get_term_meta( $this->job_id, self::JOB_RETRY_META_KEY, true );
		$retries = absint( $retries );

		return $retries;
	}

	/**
	 * Locks the process. It tells that process is running.
	 *
	 * @since n.e.x.t
	 *
	 * @return void
	 */
	public function lock() {
		$time = time();

		update_term_meta( $this->job->job_id, self::JOB_LOCK_META_KEY, $time );
		$this->job->set_status( self::JOB_STATUS_RUNNING );
	}

	/**
	 * Unlocks the process.
	 *
	 * @since n.e.x.t
	 *
	 * @return void
	 */
	public function unlock() {
		delete_term_meta( $this->job->job_id, self::JOB_LOCK_META_KEY );
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
			$this->job->set_status( self::JOB_STATUS_FAILED );

			update_term_meta( $this->job_id, self::JOB_ERRORS_META_KEY, $job_failure_data );
			update_term_meta( $this->job_id, self::JOB_RETRY_META_KEY, ( $this->get_retries() + 1 ) );
		}
	}

	/**
	 * Return max number of retries to attempt for a failed job to re-run.
	 *
	 * @since n.e.x.t
	 *
	 * @return int
	 */
	private function max_retries_allowed() {
		/**
		 * Number of attempts to try to re-run a failed job.
		 * Default 3 attempts.
		 *
		 * @since n.e.x.t
		 *
		 * @param int $retry Number of retries allowed for a job to re-run.
		 *
		 * @return int
		 */
		return apply_filters( 'perflab_job_max_retries_allowed', 3 );
	}
}
