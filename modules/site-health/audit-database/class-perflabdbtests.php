<?php
/**
 * Performance Lab database test module
 *
 * @package performance-lab
 * @group audit-database
 *
 * @since 1.4.0
 */

/**
 * Performance Lab database test class.
 *
 * This class declares and runs the database performance auditing tests.
 * It uses a passed-in PerflabDbMetrics instance to access the database.
 *
 * @package performance-lab
 * @group audit-database
 *
 * @since 1.4.0
 */
class PerflabDbTests {
	/** Metrics-retrieving instance.
	 *
	 * @var object Collection of functions.
	 */
	private $metrics;
	/** Database server semantic version and capability data.
	 *
	 * @var object Version descriptor.
	 */
	private $version;
	/** Database server name.
	 *
	 * @var string MySQL or MariaDB.
	 */
	private $name;

	/** Constructor for tests.
	 *
	 * @param object $metrics Metrics-retrieving instance.
	 */
	public function __construct( $metrics ) {
		$this->metrics = $metrics;
		$this->version = $this->metrics->get_db_version();
		$this->name    = $this->version->server_name;
	}

	/** Add all Site Health tests.
	 *
	 * @param array $tests Pre-existing tests.
	 *
	 * @return array Augmented tests.
	 */
	public function add_tests( $tests ) {
		$label                                    = __( 'Database Performance One', 'performance-lab' );
		$tests['direct']['database_performance1'] = array(
			'label' => $label,
			'test'  => array( $this, 'test1' ),
		);
		$tests['direct']['database_performance2'] = array(
			'label' => $label,
			'test'  => array( $this, 'test2' ),
		);

		return $tests;
	}

	/** Generate health-check result array.
	 *
	 * @param string $label Test label visible to user.
	 * @param string $description Test long description visible to user.
	 * @param string $actions Actions to take to correct the problem, visible to user, default ''.
	 * @param string $status 'critical', 'recommended', 'good', default 'good'.
	 *
	 * @return array
	 */
	private function test_result( $label, $description, $actions = '', $status = 'good' ) {
		return array(
			'label'       => esc_html( $label ),
			'status'      => $status,
			'description' => $description,
			'badge'       => array(
				'label' => esc_html__( 'Database Performance', 'performance-lab' ),
				'color' => 'blue',
			),
			'actions'     => is_string( $actions ) ? esc_html( $actions ) : '',
			'test'        => 'database_performance',
		);
	}

	/** First test mockup.
	 *
	 * @return array
	 */
	public function test1() {
		if ( is_string( $this->version->failure ) ) {
			return $this->test_result(
				__( 'Upgrade your outdated WordPress installation', 'performance-lab' ),
				$this->version->failure,
				$this->version->failure_action,
				'critical'
			);
		}
		if ( 0 === $this->version->unconstrained ) {
			return $this->test_result(
			/* translators: 1:  MySQL or MariaDB */
				sprintf( __( 'Your SQL server (%s) is outdated', 'performance-lab' ), $this->name ),
				sprintf(
				/* translators: 1:  MySQL or MariaDB 2: actual version of database software */
					__( 'Your %1$s SQL server is a required piece of software for WordPress\'s database. WordPress uses it to store and retrieve all your site’s content and settings. The version you use (%2$s) does not offer the fastest way to retrieve content. This affects you if you have many posts or users.', 'performance-lab' ),
					$this->name,
					$this->version->version
				),
				__( 'For optimal performance and security reasons, we recommend running MySQL version 5.7 or higher or MariaDB 10.3 or higher. Contact your web hosting company to correct this.', 'performance-lab' ),
				'recommended'
			);
		}

		return $this->test_result(
		/* translators: 1:  MySQL or MariaDB */
			sprintf( __( 'Your SQL server (%s) is a recent version', 'performance-lab' ), $this->name ),
			sprintf(
			/* translators: 1:  MySQL or MariaDB 2: actual version of database software */
				__( 'Your %1$s SQL server is a required piece of software for WordPress\'s database. WordPress uses it to store and retrieve all your site’s content and settings. The version you use (%2$s) offers the fastest way to retrieve content.', 'performance-lab' )
			)
		);
	}

	/** Second test mockup.
	 *
	 * @return array
	 */
	public function test2() {
		$result = array(
			'label'       => esc_html__( 'Whoaaaa, another test, check it out!', 'performance-lab' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => esc_html__( 'Database Performance', 'performance-lab' ),
				'color' => 'blue',
			),
			'description' => 'Description stub here.',
			'actions'     => '',
			'test'        => 'database_performance',
		);

		$result['status']         = 'critical';
		$result['badge']['color'] = 'red';
		$result['label']          = esc_html__( 'SHMITESIKES! ', 'performance-lab' );
		$result['description']    = 'more junk';

		$result['actions'] = 'deal with it!';

		return $result;
	}

}
