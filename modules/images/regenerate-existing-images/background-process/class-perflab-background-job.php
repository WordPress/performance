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
class Perflab_Background_Job {

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
	 * @param int    $job_id job ID.
	 * @param string $name   Name of the job to run/create.
	 * @param array  $data   Data for the corresponding job.
	 */
	public function __construct( $job_id = 0, $name = '', array $data = array() ) {
		$this->job_id = absint( $job_id );
		$this->name   = (string) $name;
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
	 * Create a new job in the queue.
	 *
	 * Job refers to the term and queue refers to the `background_job` taxonomy.
	 *
	 * @return int|WP_Error
	 */
	public function create() {
		// Create job only when job ID for current instance is zero.
		if ( $this->job_id > 0 ) {
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
			$this->job_id = $term_data['term_id'];

			update_term_meta( $this->job_id, $this->data, 'job_data' );

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
	 * Run this job.
	 *
	 * This needs to be implemented by concrete implementations.
	 *
	 * @return void
	 */
	public function run() {
		/**
		 * Hook to this action to run the job.
		 *
		 * Concrete implementations would add the logic to run this job.
		 *
		 * @since n.e.x.t
		 *
		 * @param int   $job_id Job ID.
		 * @param array $data   Job data.
		 */
		do_action( 'perflab_run_job_' . $this->name, $this->job_id, $this->data );
	}
}
