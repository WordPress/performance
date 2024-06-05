<?php
/**
 * Tests for load.php
 *
 * @package performance-lab
 */

class Test_Load extends WP_UnitTestCase {

	public function test_perflab_get_generator_content(): void {
		$expected = 'performance-lab ' . PERFLAB_VERSION . '; plugins: ';
		$content  = perflab_get_generator_content();
		$this->assertSame( $expected, $content );
	}

	public function test_perflab_render_generator(): void {
		$expected = '<meta name="generator" content="performance-lab ' . PERFLAB_VERSION . '; plugins: ">' . "\n";
		$output   = get_echo( 'perflab_render_generator' );
		$this->assertSame( $expected, $output );

		// Assert that the function is hooked into 'wp_head'.
		ob_start();
		do_action( 'wp_head' );
		$output = ob_get_clean();
		$this->assertStringContainsString( $expected, $output );
	}

	public function test_perflab_maybe_set_object_cache_dropin_no_conflict(): void {
		global $wp_filesystem;

		$this->set_up_mock_filesystem();

		// Ensure PL object-cache.php drop-in is not present and constant is not set.
		$this->assertFalse( $wp_filesystem->exists( WP_CONTENT_DIR . '/object-cache.php' ) );
		$this->assertFalse( PERFLAB_OBJECT_CACHE_DROPIN_VERSION );

		// Run function to place drop-in and ensure it exists afterwards.
		perflab_maybe_set_object_cache_dropin();
		$this->assertTrue( $wp_filesystem->exists( WP_CONTENT_DIR . '/object-cache.php' ) );
		$this->assertSame( file_get_contents( PERFLAB_PLUGIN_DIR_PATH . 'includes/server-timing/object-cache.copy.php' ), $wp_filesystem->get_contents( WP_CONTENT_DIR . '/object-cache.php' ) );
	}

	public function test_perflab_maybe_set_object_cache_dropin_no_conflict_but_failing(): void {
		global $wp_filesystem;

		$this->set_up_mock_filesystem();

		// Ensure PL object-cache.php drop-in is not present and constant is not set.
		$this->assertFalse( $wp_filesystem->exists( WP_CONTENT_DIR . '/object-cache.php' ) );
		$this->assertFalse( PERFLAB_OBJECT_CACHE_DROPIN_VERSION );

		// Run function to place drop-in, but then delete file, effectively
		// simulating that (for whatever reason) placing the file failed.
		perflab_maybe_set_object_cache_dropin();
		$wp_filesystem->delete( WP_CONTENT_DIR . '/object-cache.php' );

		// Running the function again should not place the file at this point,
		// as there is a transient timeout present to avoid excessive retries.
		perflab_maybe_set_object_cache_dropin();
		$this->assertFalse( $wp_filesystem->exists( WP_CONTENT_DIR . '/object-cache.php' ) );
	}

	public function test_perflab_maybe_set_object_cache_dropin_with_conflict(): void {
		global $wp_filesystem;

		$this->set_up_mock_filesystem();

		$dummy_file_content = '<?php /* Empty object-cache.php drop-in file. */';
		$wp_filesystem->put_contents( WP_CONTENT_DIR . '/object-cache.php', $dummy_file_content );

		// Ensure dummy object-cache.php drop-in is present and PL constant is not set.
		$this->assertTrue( $wp_filesystem->exists( WP_CONTENT_DIR . '/object-cache.php' ) );
		$this->assertFalse( PERFLAB_OBJECT_CACHE_DROPIN_VERSION );

		// Run function to place drop-in and ensure it does not override the existing drop-in.
		perflab_maybe_set_object_cache_dropin();
		$this->assertTrue( $wp_filesystem->exists( WP_CONTENT_DIR . '/object-cache.php' ) );
		$this->assertSame( $dummy_file_content, $wp_filesystem->get_contents( WP_CONTENT_DIR . '/object-cache.php' ) );
	}

	public function test_perflab_maybe_set_object_cache_dropin_with_older_version(): void {
		global $wp_filesystem;

		$this->set_up_mock_filesystem();

		$latest_file_content = file_get_contents( PERFLAB_PLUGIN_DIR_PATH . 'includes/server-timing/object-cache.copy.php' );
		$older_file_content  = preg_replace( '/define\( \'PERFLAB_OBJECT_CACHE_DROPIN_VERSION\', (\d+) \)\;/', "define( 'PERFLAB_OBJECT_CACHE_DROPIN_VERSION', 1 );", $latest_file_content );
		$wp_filesystem->put_contents( WP_CONTENT_DIR . '/object-cache.php', $older_file_content );

		// Simulate PL constant is set to the value from the older file.
		add_filter(
			'perflab_object_cache_dropin_version',
			static function () {
				return 1;
			}
		);

		// Ensure older object-cache.php drop-in is present.
		$this->assertTrue( $wp_filesystem->exists( WP_CONTENT_DIR . '/object-cache.php' ) );
		$this->assertSame( $older_file_content, $wp_filesystem->get_contents( WP_CONTENT_DIR . '/object-cache.php' ) );

		// Run function to place drop-in and ensure it overrides the existing drop-in with the latest version.
		perflab_maybe_set_object_cache_dropin();
		$this->assertTrue( $wp_filesystem->exists( WP_CONTENT_DIR . '/object-cache.php' ) );
		$this->assertSame( $latest_file_content, $wp_filesystem->get_contents( WP_CONTENT_DIR . '/object-cache.php' ) );
	}

	public function test_perflab_maybe_set_object_cache_dropin_with_latest_version(): void {
		global $wp_filesystem;

		$this->set_up_mock_filesystem();

		$latest_file_content = file_get_contents( PERFLAB_PLUGIN_DIR_PATH . 'includes/server-timing/object-cache.copy.php' );
		$wp_filesystem->put_contents( WP_CONTENT_DIR . '/object-cache.php', $latest_file_content );

		// Simulate PL constant is set to the value from the current file.
		$this->assertTrue( (bool) preg_match( '/define\( \'PERFLAB_OBJECT_CACHE_DROPIN_VERSION\', (\d+) \)\;/', $latest_file_content, $matches ) );
		$latest_version = (int) $matches[1];
		add_filter(
			'perflab_object_cache_dropin_version',
			static function () use ( $latest_version ) {
				return $latest_version;
			}
		);

		// Ensure latest object-cache.php drop-in is present.
		$this->assertTrue( $wp_filesystem->exists( WP_CONTENT_DIR . '/object-cache.php' ) );
		$this->assertSame( $latest_file_content, $wp_filesystem->get_contents( WP_CONTENT_DIR . '/object-cache.php' ) );

		// Run function to place drop-in and ensure it doesn't attempt to replace the file.
		perflab_maybe_set_object_cache_dropin();
		$this->assertTrue( $wp_filesystem->exists( WP_CONTENT_DIR . '/object-cache.php' ) );
		$this->assertSame( $latest_file_content, $wp_filesystem->get_contents( WP_CONTENT_DIR . '/object-cache.php' ) );
		$this->assertFalse( get_transient( 'perflab_set_object_cache_dropin' ) );
	}

	public function test_perflab_object_cache_dropin_may_be_disabled_via_filter(): void {
		global $wp_filesystem;

		$this->set_up_mock_filesystem();

		// Ensure PL object-cache.php drop-in is not present and constant is not set.
		$this->assertFalse( $wp_filesystem->exists( WP_CONTENT_DIR . '/object-cache.php' ) );
		$this->assertFalse( PERFLAB_OBJECT_CACHE_DROPIN_VERSION );

		// Add filter to disable drop-in.
		add_filter( 'perflab_disable_object_cache_dropin', '__return_true' );

		// Run function to place drop-in and ensure it still doesn't exist afterwards.
		perflab_maybe_set_object_cache_dropin();
		$this->assertFalse( $wp_filesystem->exists( WP_CONTENT_DIR . '/object-cache.php' ) );
	}

	public function test_perflab_object_cache_dropin_version_matches_latest(): void {
		$file_content = file_get_contents( PERFLAB_PLUGIN_DIR_PATH . 'includes/server-timing/object-cache.copy.php' );

		// Get the version from the file header and the constant.
		$this->assertTrue( (bool) preg_match( '/^ \* Version: (\d+)$/m', $file_content, $matches ) );
		$file_header_version = (int) $matches[1];
		$this->assertTrue( (bool) preg_match( '/define\( \'PERFLAB_OBJECT_CACHE_DROPIN_VERSION\', (\d+) \)\;/', $file_content, $matches ) );
		$file_constant_version = (int) $matches[1];

		// Assert the versions are in sync.
		$this->assertSame( PERFLAB_OBJECT_CACHE_DROPIN_LATEST_VERSION, $file_header_version );
		$this->assertSame( PERFLAB_OBJECT_CACHE_DROPIN_LATEST_VERSION, $file_constant_version );
	}

	private function set_up_mock_filesystem(): void {
		global $wp_filesystem;

		add_filter(
			'filesystem_method_file',
			static function () {
				return __DIR__ . '/data/class-wp-filesystem-mockfilesystem.php';
			}
		);
		add_filter(
			'filesystem_method',
			static function () {
				return 'MockFilesystem';
			}
		);
		WP_Filesystem();

		// Simulate that the original object-cache.copy.php file exists.
		$wp_filesystem->put_contents( PERFLAB_PLUGIN_DIR_PATH . 'includes/server-timing/object-cache.copy.php', file_get_contents( PERFLAB_PLUGIN_DIR_PATH . 'includes/server-timing/object-cache.copy.php' ) );
	}
}
