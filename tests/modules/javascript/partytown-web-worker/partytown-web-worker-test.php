<?php
/**
 * Test cases for Web Worker Module
 *
 * @package performance-lab
 */
class Web_Worker_Test extends WP_UnitTestCase {

	/**
	 * @covers ::perflab_partytown_web_worker_configuration
	 */
	public function test_perflab_partytown_web_worker_configuration() {
		$this->assertNotFalse(
			has_action( 'wp_head', 'perflab_partytown_web_worker_configuration' )
		);

		$config_output = get_echo( 'perflab_partytown_web_worker_configuration' );

		$desired_output_string_chunks = array(
			'<script>',
			'window.partytown = ',
			'lib',
			'</script>',
		);

		foreach ( $desired_output_string_chunks as $chunk ) {
			$this->assertStringContainsString( $chunk, $config_output );
		}

		// Add a filter to modify the PartyTown configuration.
		add_filter(
			'perflab_partytown_configuration',
			static function ( $config ) {
				$config['lib']   = '/partytown/';
				$config['debug'] = true;
				return $config;
			}
		);

		$config_output = get_echo( 'perflab_partytown_web_worker_configuration' );

		$desired_output_string_chunks = array(
			'<script>',
			'window.partytown = ',
			'lib',
			'/partytown',
			'debug',
			'</script>',
		);

		foreach ( $desired_output_string_chunks as $chunk ) {
			$this->assertStringContainsString( $chunk, $config_output );
		}
	}

	/**
	 * @covers ::perflab_partytown_web_worker_init
	 */
	public function test_perflab_partytown_web_worker_init() {
		$this->assertNotFalse(
			has_action(
				'wp_enqueue_scripts',
				'perflab_partytown_web_worker_init'
			)
		);

		perflab_partytown_web_worker_init();
		$this->assertTrue( wp_script_is( 'partytown', 'enqueued' ) );
	}
}
