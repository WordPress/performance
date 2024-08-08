<?php
/**
 * Tests for optimization-detective plugin optimization.php.
 *
 * @package optimization-detective
 *
 * @noinspection PhpUnhandledExceptionInspection
 * @todo There are "Cannot resolve ..." errors and "Element img doesn't have a required attribute src" warnings that should be excluded from inspection.
 */

class Test_OD_Optimization extends WP_UnitTestCase {
	use Optimization_Detective_Test_Helpers;

	/**
	 * @var string
	 */
	private $original_request_uri;

	/**
	 * @var string
	 */
	private $original_request_method;

	/**
	 * @var string
	 */
	private $default_mimetype;

	public function set_up(): void {
		$this->original_request_uri    = $_SERVER['REQUEST_URI'];
		$this->original_request_method = $_SERVER['REQUEST_METHOD'];
		$this->default_mimetype        = (string) ini_get( 'default_mimetype' );
		parent::set_up();
	}

	public function tear_down(): void {
		$_SERVER['REQUEST_URI']    = $this->original_request_uri;
		$_SERVER['REQUEST_METHOD'] = $this->original_request_method;
		ini_set( 'default_mimetype', $this->default_mimetype ); // phpcs:ignore WordPress.PHP.IniSet.Risky
		unset( $GLOBALS['wp_customize'] );
		parent::tear_down();
	}

	/**
	 * Make output is buffered and that it is also filtered.
	 *
	 * @covers ::od_buffer_output
	 */
	public function test_od_buffer_output(): void {
		$original = 'Hello World!';
		$expected = '¡Hola Mundo!';

		// In order to test, a wrapping output buffer is required because ob_get_clean() does not invoke the output
		// buffer callback. See <https://stackoverflow.com/a/61439514/93579>.
		ob_start();

		$filter_invoked = false;
		add_filter(
			'od_template_output_buffer',
			function ( $buffer ) use ( $original, $expected, &$filter_invoked ) {
				$this->assertSame( $original, $buffer );
				$filter_invoked = true;
				return $expected;
			}
		);

		$original_ob_level = ob_get_level();
		$template          = sprintf( 'page-%s.php', wp_generate_uuid4() );
		$this->assertSame( $template, od_buffer_output( $template ), 'Expected value to be passed through.' );
		$this->assertSame( $original_ob_level + 1, ob_get_level(), 'Expected call to ob_start().' );
		echo $original;

		ob_end_flush(); // Flushing invokes the output buffer callback.

		$buffer = ob_get_clean(); // Get the buffer from our wrapper output buffer.
		$this->assertSame( $expected, $buffer );
		$this->assertTrue( $filter_invoked );
	}

	/**
	 * Test that calling ob_flush() will not result in the buffer being processed and that ob_clean() will successfully prevent content from being processed.
	 *
	 * @covers ::od_buffer_output
	 */
	public function test_od_buffer_with_cleaning_and_attempted_flushing(): void {
		$template_aborted = 'Before time began!';
		$template_start   = 'The beginning';
		$template_middle  = ', the middle';
		$template_end     = ', and the end!';

		// In order to test, a wrapping output buffer is required because ob_get_clean() does not invoke the output
		// buffer callback. See <https://stackoverflow.com/a/61439514/93579>.
		$initial_level = ob_get_level();
		$this->assertTrue( ob_start() );
		$this->assertSame( $initial_level + 1, ob_get_level() );

		$filter_count = 0;
		add_filter(
			'od_template_output_buffer',
			function ( $buffer ) use ( $template_start, $template_middle, $template_end, &$filter_count ) {
				$filter_count++;
				$this->assertSame( $template_start . $template_middle . $template_end, $buffer );
				return '<filtered>' . $buffer . '</filtered>';
			}
		);

		od_buffer_output( '' );
		$this->assertSame( $initial_level + 2, ob_get_level() );

		echo $template_aborted;
		$this->assertTrue( ob_clean() ); // By cleaning, the above should never be seen by the filter.

		// This is the start of what will end up getting filtered.
		echo $template_start;

		// Attempt to flush the output, which will fail because the output buffer was opened without the flushable flag.
		$this->assertFalse( ob_flush() );

		// This will also be sent into the filter.
		echo $template_middle;
		$this->assertFalse( ob_flush() );
		$this->assertSame( $initial_level + 2, ob_get_level() );

		// Start a nested output buffer which will also end up getting sent into the filter.
		$this->assertTrue( ob_start() );
		echo $template_end;
		$this->assertSame( $initial_level + 3, ob_get_level() );
		$this->assertTrue( ob_flush() );
		$this->assertTrue( ob_end_flush() );
		$this->assertSame( $initial_level + 2, ob_get_level() );

		// Close the output buffer opened by od_buffer_output(). This only works in the unit test because the removable flag was passed.
		$this->assertTrue( ob_end_flush() );
		$this->assertSame( $initial_level + 1, ob_get_level() );

		$buffer = ob_get_clean(); // Get the buffer from our wrapper output buffer and close it.
		$this->assertSame( $initial_level, ob_get_level() );

		$this->assertSame( 1, $filter_count, 'Expected filter to be called once.' );
		$this->assertSame(
			'<filtered>' . $template_start . $template_middle . $template_end . '</filtered>',
			$buffer,
			'Excepted return value of filter to be the resulting value for the buffer.'
		);
	}

	/**
	 * Test od_maybe_add_template_output_buffer_filter().
	 *
	 * @covers ::od_maybe_add_template_output_buffer_filter
	 */
	public function test_od_maybe_add_template_output_buffer_filter(): void {
		$this->assertFalse( has_filter( 'od_template_output_buffer' ) );

		add_filter( 'od_can_optimize_response', '__return_false', 1 );
		od_maybe_add_template_output_buffer_filter();
		$this->assertFalse( od_can_optimize_response() );
		$this->assertFalse( has_filter( 'od_template_output_buffer' ) );

		add_filter( 'od_can_optimize_response', '__return_true', 2 );
		$this->go_to( home_url( '/' ) );
		$this->assertTrue( od_can_optimize_response() );
		od_maybe_add_template_output_buffer_filter();
		$this->assertTrue( has_filter( 'od_template_output_buffer' ) );
	}
	/**
	 * Test od_maybe_add_template_output_buffer_filter().
	 *
	 * @covers ::od_maybe_add_template_output_buffer_filter
	 */
	public function test_od_maybe_add_template_output_buffer_filter_with_query_var_to_disable(): void {
		$this->assertFalse( has_filter( 'od_template_output_buffer' ) );

		add_filter( 'od_can_optimize_response', '__return_true' );
		$this->go_to( home_url( '/?optimization_detective_disabled=1' ) );
		$this->assertTrue( od_can_optimize_response() );
		od_maybe_add_template_output_buffer_filter();
		$this->assertFalse( has_filter( 'od_template_output_buffer' ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_test_od_can_optimize_response(): array {
		return array(
			'homepage'           => array(
				'set_up'   => function (): void {
					$this->go_to( home_url( '/' ) );
				},
				'expected' => true,
			),
			'homepage_filtered'  => array(
				'set_up'   => function (): void {
					$this->go_to( home_url( '/' ) );
					add_filter( 'od_can_optimize_response', '__return_false' );
				},
				'expected' => false,
			),
			'search'             => array(
				'set_up'   => function (): void {
					self::factory()->post->create( array( 'post_title' => 'Hello' ) );
					$this->go_to( home_url( '?s=Hello' ) );
				},
				'expected' => false,
			),
			'customizer_preview' => array(
				'set_up'   => function (): void {
					$this->go_to( home_url( '/' ) );
					global $wp_customize;
					require_once ABSPATH . 'wp-includes/class-wp-customize-manager.php';
					$wp_customize = new WP_Customize_Manager();
					$wp_customize->start_previewing_theme();
				},
				'expected' => false,
			),
			'post_request'       => array(
				'set_up'   => function (): void {
					$this->go_to( home_url( '/' ) );
					$_SERVER['REQUEST_METHOD'] = 'POST';
				},
				'expected' => false,
			),
			'subscriber_user'    => array(
				'set_up'   => function (): void {
					wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
					$this->go_to( home_url( '/' ) );
				},
				'expected' => true,
			),
			'admin_user'         => array(
				'set_up'   => function (): void {
					wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
					$this->go_to( home_url( '/' ) );
				},
				'expected' => false,
			),
		);
	}

	/**
	 * Test od_can_optimize_response().
	 *
	 * @covers ::od_can_optimize_response
	 *
	 * @dataProvider data_provider_test_od_can_optimize_response
	 */
	public function test_od_can_optimize_response( Closure $set_up, bool $expected ): void {
		$set_up();
		$this->assertSame( $expected, od_can_optimize_response() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_test_od_optimize_template_output_buffer(): array {
		$test_cases = array();
		foreach ( (array) glob( __DIR__ . '/test-cases/*.php' ) as $test_case ) {
			$name                = basename( $test_case, '.php' );
			$test_cases[ $name ] = require $test_case;
		}
		return $test_cases;
	}

	/**
	 * Test od_optimize_template_output_buffer().
	 *
	 * @covers ::od_optimize_template_output_buffer
	 * @covers ::od_is_response_html_content_type
	 *
	 * @dataProvider data_provider_test_od_optimize_template_output_buffer
	 */
	public function test_od_optimize_template_output_buffer( Closure $set_up, string $buffer, string $expected ): void {
		$set_up( $this );

		add_action(
			'od_register_tag_visitors',
			function ( OD_Tag_Visitor_Registry $tag_visitor_registry ): void {
				$tag_visitor_registry->register(
					'img',
					function ( OD_Tag_Visitor_Context $context ): bool {
						$this->assertFalse( $context->processor->is_tag_closer() );
						return $context->processor->get_tag() === 'IMG';
					}
				);
			}
		);

		add_action(
			'od_register_tag_visitors',
			function ( OD_Tag_Visitor_Registry $tag_visitor_registry ): void {
				$tag_visitor_registry->register(
					'video',
					function ( OD_Tag_Visitor_Context $context ): bool {
						$this->assertFalse( $context->processor->is_tag_closer() );
						return $context->processor->get_tag() === 'VIDEO';
					}
				);
			}
		);

		$buffer = preg_replace(
			':<script type="module">.+?</script>:s',
			'<script type="module">/* import detect ... */</script>',
			od_optimize_template_output_buffer( $buffer )
		);

		$this->assertEquals(
			$this->remove_initial_tabs( $expected ),
			$this->remove_initial_tabs( $buffer ),
			"Buffer snapshot:\n$buffer"
		);
	}
}
