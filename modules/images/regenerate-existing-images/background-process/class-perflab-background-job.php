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
 * Runs the heavy lifting tasks in background in separate process.
 */
final class Perflab_Background_Job {
	/**
	 * Meta key for storing job name/action.
	 *
	 * @const string
	 */
	const JOB_NAME_META_KEY = 'job_name';

	/**
	 * Meta key for storing job data.
	 *
	 * @const string
	 */
	const JOB_DATA_META_KEY = 'job_data';

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
	 * @param int $job_id job ID.
	 */
	public function __construct( $job_id = 0 ) {
		$this->job_id = absint( $job_id );

		// @todo Replace taxonomy name with constant.
		if ( term_exists( $this->job_id, 'background_job' ) ) {
			$this->name = get_term_meta( $this->job_id, Perflab_Background_Job::JOB_NAME_META_KEY, true );
			$this->data = get_term_meta( $this->job_id, Perflab_Background_Job::JOB_DATA_META_KEY, true );
		}
	}

	/**
	 * Checks that the job term exist.
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
	 * @return int
	 */
	public function is_running() {
		$lock = get_term_meta( $this->job_id, 'job_lock', true );
		$lock = empty( $lock ) ? 0 : $lock;

		return absint( $lock );
	}

	/**
	 * Checks if the background job is completed.
	 *
	 * @return bool
	 */
	public function is_complete() {
		return (bool) get_term_meta( $this->job_id, 'job_completed_at', true );
	}

	/**
	 * Sets the status of the current job.
	 *
	 * @param string $status Status to set for current job.
	 *
	 * @return void
	 */
	public function set_status( $status ) {
		$valid_statuses = array(
			'running',
			'failed',
			'complete',
		);

		if ( in_array( $status, $valid_statuses, true ) ) {
			update_term_meta( $this->job_id, 'job_status', $status );

			// If job is complete, set the timestamp at which it was completed.
			if ( 'complete' === $status ) {
				update_term_meta( $this->job_id, 'job_completed_at', time() );
			}
		}
	}

	/**
	 * Retrieves the job data.
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

		$this->data = get_term_meta( $this->job_id, 'job_data', true );

		return $this->data;
	}

	/**
	 * Creates a new job in the queue.
	 *
	 * Job refers to the term and queue refers to the `background_job` taxonomy.
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

			update_term_meta( $this->job_id, Perflab_Background_Job::JOB_DATA_META_KEY, $this->data );
			update_term_meta( $this->job_id, Perflab_Background_Job::JOB_NAME_META_KEY, $this->name );

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
	 * Locks the process. It tells that process is running.
	 *
	 * @return void
	 */
	public function lock() {
		$time = time();

		update_term_meta( $this->job->job_id, 'job_lock', $time );
		$this->job->set_status( 'running' );
	}

	/**
	 * Unlocks the process.
	 *
	 * @return void
	 */
	public function unlock() {
		delete_term_meta( $this->job->job_id, 'job_lock' );
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
		$job_failure_data = $error->get_error_data( 'perflab_job_failure' );

		if ( ! empty( $job_failure_data ) ) {
			$retries = get_term_meta( $this->job_id, 'perflab_job_retries', true );
			$retries = empty( $retries ) ? 0 : ( absint( $retries ) + 1 ); // Increment retry count.

			$this->job->set_status( 'failed' );

			update_term_meta( $this->job_id, 'job_errors', $job_failure_data );
			update_term_meta( $this->job_id, 'perflab_job_retries', $$retries );
		}
	}
}
