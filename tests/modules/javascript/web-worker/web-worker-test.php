<?php
/**
 * Test cases for Web Worker Module
 *
 * @package performance-lab
 */
class Web_Worker_Test extends WP_UnitTestCase {

	/**
	 * @covers ::web_worker_partytown_configuration
	 */
	function test_web_worker_partytown_configuration() {
		$this->assertEquals(
			1,
			has_action( 'wp_head', 'web_worker_partytown_configuration' )
		);

		ob_start();
		web_worker_partytown_configuration();
		$config_output = ob_get_clean();

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
			'partytown_configuration',
			function ( $config ) {
				$config['lib']   = '/partytown/';
				$config['debug'] = true;
				return $config;
			}
		);

		ob_start();
		web_worker_partytown_configuration();
		$config_output = ob_get_clean();

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
	 * @covers ::web_worker_partytown_init
	 */
	function test_web_worker_partytown_init() {
		$this->assertEquals(
			1,
			has_action(
				'wp_enqueue_scripts',
				'web_worker_partytown_init'
			)
		);

		web_worker_partytown_init();
		$this->assertTrue( wp_script_is( 'partytown', 'enqueued' ) );
	}

	/**
	 * @covers ::web_worker_partytown_worker_scripts
	 */
	function test_web_worker_partytown_worker_scripts() {
		$this->assertEquals(
			10,
			has_action( 'wp_print_scripts', 'web_worker_partytown_worker_scripts' )
		);

		$this->assertEmpty( $this->get_partytown_handles() );

		// Create some scripts with `partytown` dependency.
		$script_handles = array(
			'non-critical-js',
			'analytics-js',
			'third-party-js',
		);

		foreach ( $script_handles as $handle ) {
			$src  = plugin_dir_url( __FILE__ ) . 'assets/js/' . $handle . '.js';
			$deps = array( 'partytown' );
			wp_enqueue_script( $handle, $src, $deps, PERFLAB_VERSION, false );
		}

		$this->assertTrue( wp_script_is( 'partytown', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'non-critical-js', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'analytics-js', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'third-party-js', 'enqueued' ) );

		web_worker_partytown_worker_scripts();

		$this->assertEquals(
			$script_handles,
			$this->get_partytown_handles()
		);

		$expected_scripts_chunk = '<script type="text/partytown" src="http://example.org/wp-content/plugins/performance/tests/modules/javascript/web-worker/assets/js/non-critical-js.js?ver=1.0.0-beta.3" id="non-critical-js-js"></script><script type="text/partytown" src="http://example.org/wp-content/plugins/performance/tests/modules/javascript/web-worker/assets/js/analytics-js.js?ver=1.0.0-beta.3" id="analytics-js-js"></script><script type="text/partytown" src="http://example.org/wp-content/plugins/performance/tests/modules/javascript/web-worker/assets/js/third-party-js.js?ver=1.0.0-beta.3" id="third-party-js-js"></script>';

		ob_start();
		wp_print_scripts();
		$scripts_output = ob_get_clean();

		/*
		 * $scripts_output also contains the script tag for the partytown.js, so only check for such scripts
		 * which have `partytown` as a dependency.
		 */
		$this->assertStringContainsString( $expected_scripts_chunk, $scripts_output );
	}

	/**
	 * Helper function to get all scripts tags which has `partytown` dependency.
	 */
	function get_partytown_handles() {
		global $wp_scripts;

		$partytown_handles = array();
		foreach ( $wp_scripts->registered as $handle => $script ) {
			if ( ! empty( $script->deps ) && in_array( 'partytown', $script->deps, true ) ) {
				$partytown_handles[] = $handle;
			}
		}

		return $partytown_handles;
	}
}
