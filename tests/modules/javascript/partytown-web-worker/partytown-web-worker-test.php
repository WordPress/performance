<?php
/**
 * Test cases for Web Worker Module
 *
 * @package performance-lab
 */
class Web_Worker_Test extends WP_UnitTestCase {

	/**
	 * @covers ::perflab_web_worker_partytown_configuration
	 */
	function test_perflab_web_worker_partytown_configuration() {
		$this->assertNotFalse(
			has_action( 'wp_head', 'perflab_web_worker_partytown_configuration' )
		);

		$config_output = get_echo( 'perflab_web_worker_partytown_configuration' );

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

		$config_output = get_echo( 'perflab_web_worker_partytown_configuration' );

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
	 * @covers ::perflab_web_worker_partytown_init
	 */
	function test_perflab_web_worker_partytown_init() {
		$this->assertNotFalse(
			has_action(
				'wp_enqueue_scripts',
				'perflab_web_worker_partytown_init'
			)
		);

		perflab_web_worker_partytown_init();
		$this->assertTrue( wp_script_is( 'partytown', 'enqueued' ) );
	}

	/**
	 * @covers ::perflab_web_worker_partytown_worker_scripts
	 * @covers ::perflab_get_partytown_handles
	 */
	function test_perflab_web_worker_partytown_worker_scripts() {
		global $wp_scripts;

		$this->assertNotFalse(
			has_action( 'wp_print_scripts', 'perflab_web_worker_partytown_worker_scripts' )
		);

		$this->assertEmpty( perflab_get_partytown_handles() );

		// Create some scripts with `partytown` dependency.
		$script_handles = array(
			'non-critical-js',
			'analytics-js',
			'third-party-js',
		);

		$store_src = array();
		foreach ( $script_handles as $handle ) {
			$src                  = plugin_dir_url( __FILE__ ) . 'assets/js/' . $handle . '.js';
			$store_src[ $handle ] = $src;
			$deps                 = array( 'partytown' );
			wp_enqueue_script( $handle, $src, $deps, PERFLAB_VERSION, false );
		}

		$this->assertTrue( wp_script_is( 'partytown', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'non-critical-js', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'analytics-js', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'third-party-js', 'enqueued' ) );

		perflab_web_worker_partytown_worker_scripts();

		$this->assertEquals(
			$script_handles,
			perflab_get_partytown_handles()
		);

		$expected_scripts_chunk = '';
		foreach ( $script_handles as $handle ) {
			$expected_scripts_chunk .= sprintf(
				'<script type="text/partytown" src="%1$s" id="%2$s"></script>',
				$store_src[ $handle ] . '?ver=' . PERFLAB_VERSION,
				$handle . '-js'
			);
		}

		$scripts_output = get_echo( 'wp_print_scripts' );

		/*
		 * $scripts_output also contains the script tag for the partytown.js, so only check for such scripts
		 * which have `partytown` as a dependency.
		 */
		$this->assertStringContainsString( $expected_scripts_chunk, $scripts_output );

		// Remove scripts.
		$remove_scripts = array_merge( $script_handles, array( 'partytown' ) );
		foreach ( $remove_scripts as $handle ) {
			wp_dequeue_script( $handle );
		}

		/*
		 * Unset $wp_scripts->done as it get saved into transients of enqueued_scripts.
		 * @see perflab_aea_audit_enqueued_scripts()
		 */
		$wp_scripts->done = array();
	}
}
