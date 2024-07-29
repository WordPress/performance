<?php
/**
 * Tests for audit-autoloaded-options check.
 *
 * @package performance-lab
 */
class Test_Audit_Autoloaded_Options extends WP_UnitTestCase {

	const WARNING_AUTOLOADED_SIZE_LIMIT_IN_BYTES = 800000;
	const AUTOLOADED_OPTION_KEY                  = 'test_set_autoloaded_option';

	/**
	 * Tests perflab_aao_add_autoloaded_options_test()
	 */
	public function test_perflab_aao_add_autoloaded_options_test(): void {
		$expected_test = array(
			'label' => esc_html__( 'Autoloaded options', 'performance-lab' ),
			'test'  => 'perflab_aao_autoloaded_options_test',
		);

		$initial_test = array(
			'label' => 'Label',
			'test'  => 'initial_test',
		);
		$this->assertEquals(
			array(
				'direct' => array(
					'initial'            => $initial_test,
					'autoloaded_options' => $expected_test,
				),
			),
			perflab_aao_add_autoloaded_options_test( array( 'direct' => array( 'initial' => $initial_test ) ) )
		);
	}

	/**
	 * Tests perflab_aao_autoloaded_options_test() when autoloaded options less than warning size.
	 */
	public function test_perflab_aao_autoloaded_options_test_no_warning(): void {
		$expected_label  = esc_html__( 'Autoloaded options are acceptable', 'performance-lab' );
		$expected_status = 'good';

		$result = perflab_aao_autoloaded_options_test();
		$this->assertSame( $expected_label, $result['label'] );
		$this->assertSame( $expected_status, $result['status'] );
	}

	/**
	 * Tests perflab_aao_autoloaded_options_test() when autoloaded options more than warning size.
	 */
	public function test_perflab_aao_autoloaded_options_test_warning(): void {
		self::set_autoloaded_option( self::WARNING_AUTOLOADED_SIZE_LIMIT_IN_BYTES );

		$expected_label  = esc_html__( 'Autoloaded options could affect performance', 'performance-lab' );
		$expected_status = 'critical';

		$result = perflab_aao_autoloaded_options_test();
		$this->assertSame( $expected_label, $result['label'] );
		$this->assertSame( $expected_status, $result['status'] );
	}

	/**
	 * Tests perflab_aao_autoloaded_options_size()
	 *
	 * @covers ::perflab_aao_autoloaded_options_size
	 */
	public function test_perflab_aao_autoloaded_options_size(): void {
		global $wpdb;

		$autoload_values = perflab_aao_get_autoload_values_to_autoload();

		$autoloaded_options_size = $wpdb->get_var(
			$wpdb->prepare(
				sprintf(
					"SELECT SUM(LENGTH(option_value)) FROM $wpdb->options WHERE autoload IN (%s)",
					implode( ',', array_fill( 0, count( $autoload_values ), '%s' ) )
				),
				$autoload_values
			)
		);
		$this->assertEquals( $autoloaded_options_size, perflab_aao_autoloaded_options_size() );

		// Add autoload options.
		$new_autoload_options = array(
			'string' => 'test',
			'array'  => array( 1, 2, 3 ),
			'object' => (object) array( 'foo' => 'bar' ),
		);

		$expected_size_increase = 0;
		foreach ( $new_autoload_options as $key => $value ) {
			add_option( "new_autoload_{$key}", $value, '', true );
			$expected_size_increase += mb_strlen( maybe_serialize( $value ), '8bit' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		}

		$this->assertSame( $autoloaded_options_size + $expected_size_increase, perflab_aao_autoloaded_options_size() );

		// Now try faking a misconfigured object cache which returns unserialized values from wp_load_alloptions().
		add_filter(
			'alloptions',
			static function ( array $alloptions ): array {
				return array_map(
					static function ( $option ) {
						if ( is_serialized( $option ) ) {
							return unserialize( $option ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
						} else {
							return $option;
						}
					},
					$alloptions
				);
			}
		);

		$this->assertSame( $autoloaded_options_size + $expected_size_increase, perflab_aao_autoloaded_options_size() );
	}

	public function test_perflab_aao_autoloaded_options_disable_revert_functionality(): void {

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Mock wp_redirect to avoid actual redirection.
		add_filter( 'wp_redirect', '__return_false' );

		// Add an autoload option with small size length value for testing.
		$test_option_string       = 'test';
		$test_option_string_bytes = mb_strlen( $test_option_string, '8bit' );
		self::set_autoloaded_option( $test_option_string_bytes );

		// Test that the autoloaded option is not displayed in the table because value lower then it's threshold value.
		$table_html = perflab_aao_get_autoloaded_options_table();
		$this->assertStringNotContainsString( self::AUTOLOADED_OPTION_KEY, $table_html );

		// Delete autoloaded option.
		self::delete_autoloaded_option();

		// Add an autoload option with bigger size length value for testing.
		self::set_autoloaded_option( self::WARNING_AUTOLOADED_SIZE_LIMIT_IN_BYTES );

		$table_html = perflab_aao_get_autoloaded_options_table();
		$this->assertStringContainsString( self::AUTOLOADED_OPTION_KEY, $table_html );

		// Check disable autoloaded option functionality.
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'perflab_aao_update_autoload' );
		$_GET['action']       = 'perflab_aao_update_autoload';
		$_GET['option_name']  = self::AUTOLOADED_OPTION_KEY;
		$_GET['autoload']     = 'false';

		perflab_aao_handle_update_autoload();

		// Test that the autoloaded option is not displayed in the perflab_aao_get_autoloaded_options_table() after disabling.
		$table_html = perflab_aao_get_autoloaded_options_table();
		$this->assertStringNotContainsString( self::AUTOLOADED_OPTION_KEY, $table_html );

		// Test that the disabled autoloaded option is displayed in the disabled options perflab_aao_get_disabled_autoloaded_options_table().
		$table_html = perflab_aao_get_disabled_autoloaded_options_table();
		$this->assertStringContainsString( self::AUTOLOADED_OPTION_KEY, $table_html );

		// Revert the disabled autoloaded option.
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'perflab_aao_update_autoload' );
		$_GET['action']       = 'perflab_aao_update_autoload';
		$_GET['option_name']  = self::AUTOLOADED_OPTION_KEY;
		$_GET['autoload']     = 'true';

		perflab_aao_handle_update_autoload();

		// Test that the disabled autoloaded option is not displayed in the disabled options perflab_aao_get_disabled_autoloaded_options_table() after reverting.
		$table_html = perflab_aao_get_disabled_autoloaded_options_table();
		$this->assertStringNotContainsString( self::AUTOLOADED_OPTION_KEY, $table_html );

		// Test that the reverted autoloaded option is displayed in the autoloaded options perflab_aao_get_autoloaded_options_table().
		$table_html = perflab_aao_get_autoloaded_options_table();
		$this->assertStringContainsString( self::AUTOLOADED_OPTION_KEY, $table_html );
	}

	/**
	 * Test that the list of disabled options excludes options that are autoloaded.
	 *
	 * @covers ::perflab_filter_option_perflab_aao_disabled_options
	 */
	public function test_perflab_aao_autoloaded_options_auto_enable_functionality(): void {

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Mock wp_redirect to avoid actual redirection.
		add_filter( 'wp_redirect', '__return_false' );

		// Add an autoload option with bigger size length value for testing.
		self::set_autoloaded_option( self::WARNING_AUTOLOADED_SIZE_LIMIT_IN_BYTES );

		$table_html = perflab_aao_get_autoloaded_options_table();
		$this->assertStringContainsString( self::AUTOLOADED_OPTION_KEY, $table_html );

		// Check disable autoloaded option functionality.
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'perflab_aao_update_autoload' );
		$_GET['action']       = 'perflab_aao_update_autoload';
		$_GET['option_name']  = self::AUTOLOADED_OPTION_KEY;
		$_GET['autoload']     = 'false';

		perflab_aao_handle_update_autoload();

		// Test that the autoloaded option is not displayed in the perflab_aao_get_autoloaded_options_table() after disabling.
		$table_html = perflab_aao_get_autoloaded_options_table();
		$this->assertStringNotContainsString( self::AUTOLOADED_OPTION_KEY, $table_html );

		// The option already exists, so update it.
		update_option( self::AUTOLOADED_OPTION_KEY, wp_generate_password( self::WARNING_AUTOLOADED_SIZE_LIMIT_IN_BYTES ), 'yes' );

		$table_html = perflab_aao_get_autoloaded_options_table();
		$this->assertStringContainsString( self::AUTOLOADED_OPTION_KEY, $table_html );

		// Test that the disabled autoloaded option is displayed in the disabled options perflab_aao_get_disabled_autoloaded_options_table().
		$table_html = perflab_aao_get_disabled_autoloaded_options_table();
		$this->assertStringNotContainsString( self::AUTOLOADED_OPTION_KEY, $table_html );
	}

	/**
	 * Sets a test autoloaded option.
	 *
	 * @param int $bytes bytes to load in options.
	 */
	public static function set_autoloaded_option( int $bytes = 800000 ): void {
		$heavy_option_string = wp_generate_password( $bytes );

		// Force autoloading so that WordPress core does not override it. See https://core.trac.wordpress.org/changeset/57920.
		add_option( self::AUTOLOADED_OPTION_KEY, $heavy_option_string, '', true );
	}

	/**
	 * Deletes test autoloaded option.
	 */
	public static function delete_autoloaded_option(): void {
		delete_option( self::AUTOLOADED_OPTION_KEY );
	}
}
