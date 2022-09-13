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
	 * @var string
	 */
	const META_KEY_JOB_NAME = 'perflab_job_name';

	/**
	 * Meta key for storing job data.
	 *
	 * @since n.e.x.t
	 * @var string
	 */
	const META_KEY_JOB_DATA = 'perflab_job_data';

	/**
	 * Meta key for storing job data.
	 *
	 * @since n.e.x.t
	 * @var string
	 */
	const META_KEY_JOB_ATTEMPTS = 'perflab_job_attempts';

	/**
	 * Meta key for storing job lock.
	 *
	 * @since n.e.x.t
	 * @var string
	 */
	const META_KEY_JOB_LOCK = 'perflab_job_lock';

	/**
	 * Meta key for storing job errors.
	 *
	 * @since n.e.x.t
	 * @var string
	 */
	const META_KEY_JOB_ERRORS = 'perflab_job_errors';

	/**
	 * Job status meta key.
	 *
	 * @since n.e.x.t
	 * @var string
	 */
	const META_KEY_JOB_STATUS = 'perflab_job_status';

	/**
	 * Job status for queued jobs.
	 *
	 * @since n.e.x.t
	 * @var string
	 */
	const JOB_STATUS_QUEUED = 'perflab_job_queued';

	/**
	 * Job status for running jobs.
	 *
	 * @since n.e.x.t
	 * @var string
	 */
	const JOB_STATUS_RUNNING = 'perflab_job_running';

	/**
	 * Job status for partially executed jobs.
	 *
	 * @since n.e.x.t
	 * @var string
	 */
	const JOB_STATUS_PARTIAL = 'perflab_job_partial';

	/**
	 * Job status for completed jobs.
	 *
	 * @since n.e.x.t
	 * @var string
	 */
	const JOB_STATUS_COMPLETE = 'perflab_job_complete';

	/**
	 * Job status for failed jobs.
	 *
	 * @since n.e.x.t
	 * @var string
	 */
	const JOB_STATUS_FAILED = 'perflab_job_failed';

	/**
	 * Job ID.
	 *
	 * @since n.e.x.t
	 * @var int
	 */
	private $id;

	/**
	 * Job name.
	 *
	 * @since n.e.x.t
	 * @var string
	 */
	private $name;

	/**
	 * Job data.
	 *
	 * @since n.e.x.t
	 * @var array
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * @since n.e.x.t
	 *
	 * @param int $job_id Job ID. Technically this is the ID of a term in the 'background_job' taxonomy.
	 */
	public function __construct( $job_id ) {
		$this->id = absint( $job_id );
	}

	/**
	 * Returns the id for the job.
	 *
	 * @return int Job ID. Technically this is a term id for `background_job` taxonomy.
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Retrieves the job data.
	 *
	 * @since n.e.x.t
	 *
	 * @return array|null Job data.
	 */
	public function get_data() {
		$this->data = get_term_meta( $this->id, self::META_KEY_JOB_DATA, true );

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
		$this->name = get_term_meta( $this->id, self::META_KEY_JOB_NAME, true );

		return $this->name;
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
		if ( $this->get_attempts() >= absint( $max_attempts ) ) {
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
			update_term_meta( $this->id, self::META_KEY_JOB_STATUS, $status );

			// If job is complete, set the timestamp at which it was completed.
			if ( self::JOB_STATUS_COMPLETE === $status ) {
				update_term_meta( $this->id, 'job_completed_at', time() );
			}

			return true;
		}

		return false;
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
	public function set_error( WP_Error $error ) {
		$job_failure_data = $error->get_error_data( 'perflab_job_failure' );

		if ( ! empty( $job_failure_data ) ) {
			$this->set_status( self::JOB_STATUS_FAILED );

			update_term_meta( $this->id, self::META_KEY_JOB_ERRORS, $job_failure_data );
			update_term_meta( $this->id, self::META_KEY_JOB_ATTEMPTS, ( $this->get_attempts() + 1 ) );
		}
	}

	/**
	 * Retrieves the job status from its meta information.
	 *
	 * @since n.e.x.t
	 *
	 * @return string Job status.
	 */
	public function get_status() {
		return (string) get_term_meta( $this->id, self::META_KEY_JOB_STATUS, true );
	}

	/**
	 * Returns the number of attempts executed for a job.
	 *
	 * @since n.e.x.t
	 *
	 * @return int
	 */
	public function get_attempts() {
		$attempts = get_term_meta( $this->id, self::META_KEY_JOB_ATTEMPTS, true );

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
		update_term_meta( $this->id, self::META_KEY_JOB_LOCK, $time );
		$this->set_status( self::JOB_STATUS_RUNNING );
	}

	/**
	 * Get the timestamp (in seconds) when the job was started.
	 *
	 * @since n.e.x.t
	 *
	 * @return int Start time of the job.
	 */
	public function get_start_time() {
		$time = get_term_meta( $this->id, self::META_KEY_JOB_LOCK, true );

		return absint( $time );
	}

	/**
	 * Unlocks the process.
	 *
	 * Mark job status as partial as the unlock implies that job has run
	 * atleast partially.
	 *
	 * @since n.e.x.t
	 *
	 * @return void
	 */
	public function unlock() {
		delete_term_meta( $this->id, self::META_KEY_JOB_LOCK );
		$this->set_status( self::JOB_STATUS_PARTIAL );
	}

	/**
	 * Checks if the background job is completed.
	 *
	 * @since n.e.x.t
	 *
	 * @return bool
	 */
	public function is_completed() {
		return ( self::JOB_STATUS_COMPLETE === $this->get_status() );
	}

	/**
	 * Checks that the job term exist.
	 *
	 * @since n.e.x.t
	 *
	 * @return array|null
	 */
	public function exists() {
		return term_exists( $this->id, PERFLAB_BACKGROUND_JOB_TAXONOMY_SLUG );
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
}
