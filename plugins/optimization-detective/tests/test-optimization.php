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
		parent::set_up();
		$this->original_request_uri    = $_SERVER['REQUEST_URI'];
		$this->original_request_method = $_SERVER['REQUEST_METHOD'];
		$this->default_mimetype        = (string) ini_get( 'default_mimetype' );
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
	 * Data provider.
	 *
	 * @return array<string, mixed>
	 */
	public function data_provider_test_od_maybe_add_template_output_buffer_filter(): array {
		return array(
			'home_enabled'                          => array(
				'set_up'              => static function (): string {
					return home_url( '/' );
				},
				'expected_has_filter' => true,
			),
			'home_disabled_by_filter'               => array(
				'set_up'              => static function (): string {
					add_filter( 'od_can_optimize_response', '__return_false' );
					return home_url( '/' );
				},
				'expected_has_filter' => false,
			),
			'search_disabled'                       => array(
				'set_up'              => static function (): string {
					return home_url( '/?s=foo' );
				},
				'expected_has_filter' => false,
			),
			'search_enabled_by_filter'              => array(
				'set_up'              => static function (): string {
					add_filter( 'od_can_optimize_response', '__return_true' );
					return home_url( '/?s=foo' );
				},
				'expected_has_filter' => true,
			),
			'home_disabled_by_get_param'            => array(
				'set_up'              => static function (): string {
					return home_url( '/?optimization_detective_disabled=1' );
				},
				'expected_has_filter' => false,
			),
			'home_disabled_by_rest_api_unavailable' => array(
				'set_up'              => static function (): string {
					update_option( 'od_rest_api_unavailable', '1' );
					return home_url( '/' );
				},
				'expected_has_filter' => false,
			),
		);
	}

	/**
	 * Test od_maybe_add_template_output_buffer_filter().
	 *
	 * @dataProvider data_provider_test_od_maybe_add_template_output_buffer_filter
	 *
	 * @covers ::od_maybe_add_template_output_buffer_filter
	 * @covers ::od_can_optimize_response
	 * @covers ::od_is_rest_api_unavailable
	 */
	public function test_od_maybe_add_template_output_buffer_filter( Closure $set_up, bool $expected_has_filter ): void {
		// There needs to be a post so that there is a post in the loop so that od_get_cache_purge_post_id() returns a post ID.
		// Otherwise, od_can_optimize_response() will return false unless forced by a filter.
		self::factory()->post->create();

		$url = $set_up();
		$this->go_to( $url );
		remove_all_filters( 'od_template_output_buffer' ); // In case go_to() caused them to be added.

		od_maybe_add_template_output_buffer_filter();
		$this->assertSame( $expected_has_filter, has_filter( 'od_template_output_buffer' ) );
	}

	/**
	 * Test od_print_disabled_reasons().
	 *
	 * @covers ::od_print_disabled_reasons
	 */
	public function test_od_print_disabled_reasons(): void {
		$this->assertSame( '', get_echo( 'od_print_disabled_reasons', array( array() ) ) );
		$foo_message = get_echo( 'od_print_disabled_reasons', array( array( 'Foo' ) ) );
		$this->assertStringContainsString( '<script', $foo_message );
		$this->assertStringContainsString( 'console.info(', $foo_message );
		$this->assertStringContainsString( '[Optimization Detective] Foo', $foo_message );

		$foo_and_bar_messages = get_echo( 'od_print_disabled_reasons', array( array( 'Foo', 'Bar' ) ) );
		$this->assertStringContainsString( '<script', $foo_and_bar_messages );
		$this->assertStringContainsString( 'console.info(', $foo_and_bar_messages );
		$this->assertStringContainsString( '[Optimization Detective] Foo', $foo_and_bar_messages );
		$this->assertStringContainsString( '[Optimization Detective] Bar', $foo_and_bar_messages );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_test_od_can_optimize_response(): array {
		return array(
			'home_as_anonymous'                    => array(
				'set_up'   => function (): void {
					$this->go_to( home_url( '/' ) );
					$this->assertIsInt( od_get_cache_purge_post_id() );
				},
				'expected' => true,
			),
			'home_but_no_posts'                    => array(
				'set_up'   => function (): void {
					$posts = get_posts();
					foreach ( $posts as $post ) {
						wp_delete_post( $post->ID, true );
					}
					$this->go_to( home_url( '/' ) );
					$this->assertNull( od_get_cache_purge_post_id() );
				},
				'expected' => false, // This is because od_get_cache_purge_post_id() will return false.
			),
			'home_filtered_as_anonymous'           => array(
				'set_up'   => static function (): string {
					add_filter( 'od_can_optimize_response', '__return_false' );
					return home_url( '/' );
				},
				'expected' => false,
			),
			'singular_as_anonymous'                => array(
				'set_up'   => function (): string {
					$posts = get_posts();
					$this->assertInstanceOf( WP_Post::class, $posts[0] );
					return get_permalink( $posts[0] );
				},
				'expected' => true,
			),
			'search_as_anonymous'                  => array(
				'set_up'   => static function (): string {
					self::factory()->post->create( array( 'post_title' => 'Hello' ) );
					return home_url( '?s=Hello' );
				},
				'expected' => false,
			),
			'home_customizer_preview_as_anonymous' => array(
				'set_up'   => static function (): string {
					global $wp_customize;
					require_once ABSPATH . 'wp-includes/class-wp-customize-manager.php';
					$wp_customize = new WP_Customize_Manager();
					$wp_customize->start_previewing_theme();
					return home_url( '/' );
				},
				'expected' => false,
			),
			'home_post_request_as_anonymous'       => array(
				'set_up'   => static function (): string {
					$_SERVER['REQUEST_METHOD'] = 'POST';
					return home_url( '/' );
				},
				'expected' => false,
			),
			'home_as_subscriber'                   => array(
				'set_up'   => static function (): string {
					wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
					return home_url( '/' );
				},
				'expected' => true,
			),
			'empty_author_page_as_anonymous'       => array(
				'set_up'   => static function (): string {
					$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
					return get_author_posts_url( $user_id );
				},
				'expected' => false,
			),
			'home_as_admin'                        => array(
				'set_up'   => static function (): string {
					wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
					return home_url( '/' );
				},
				'expected' => true,
			),
		);
	}

	/**
	 * Test od_can_optimize_response().
	 *
	 * @covers ::od_can_optimize_response
	 * @covers ::od_get_cache_purge_post_id
	 *
	 * @dataProvider data_provider_test_od_can_optimize_response
	 */
	public function test_od_can_optimize_response( Closure $set_up, bool $expected ): void {
		// Make sure there is at least one post in the DB as otherwise od_get_cache_purge_post_id() will return false,
		// causing od_can_optimize_response() to return false.
		self::factory()->post->create();

		$url = $set_up();
		$this->go_to( $url );
		$this->assertSame( $expected, od_can_optimize_response() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array{ directory: non-empty-string }> Test cases.
	 */
	public function data_provider_test_od_optimize_template_output_buffer(): array {
		return $this->load_snapshot_test_cases( __DIR__ . '/test-cases' );
	}

	/**
	 * Test od_optimize_template_output_buffer().
	 *
	 * @covers ::od_optimize_template_output_buffer
	 * @covers ::od_is_response_html_content_type
	 * @covers OD_Tag_Visitor_Context::__construct
	 * @covers OD_Tag_Visitor_Context::__get
	 * @covers OD_Tag_Visitor_Context::track_tag
	 * @covers OD_Visited_Tag_State::__construct
	 * @covers OD_Visited_Tag_State::track_tag
	 * @covers OD_Visited_Tag_State::is_tag_tracked
	 * @covers OD_Visited_Tag_State::reset
	 *
	 * @dataProvider data_provider_test_od_optimize_template_output_buffer
	 *
	 * @param non-empty-string $directory Test case directory.
	 *
	 * @noinspection PhpDocMissingThrowsInspection
	 */
	public function test_od_optimize_template_output_buffer( string $directory ): void {
		add_action(
			'od_register_tag_visitors',
			function ( OD_Tag_Visitor_Registry $tag_visitor_registry ): void {
				$tag_visitor_registry->register(
					'img',
					function ( OD_Tag_Visitor_Context $context ): bool {
						$this->assertInstanceOf( OD_URL_Metric_Group_Collection::class, $context->url_metric_group_collection );
						$this->setExpectedIncorrectUsage( 'OD_Tag_Visitor_Context::$url_metrics_group_collection' );
						$this->assertInstanceOf( OD_URL_Metric_Group_Collection::class, $context->url_metrics_group_collection );
						$this->assertInstanceOf( OD_HTML_Tag_Processor::class, $context->processor );
						$this->assertInstanceOf( OD_Link_Collection::class, $context->link_collection );

						$error = null;
						$value = '';
						try {
							$value = $context->__get( 'invalid_param' );
						} catch ( Error $e ) {
							$error = $e;
						}
						$this->assertInstanceOf( Error::class, $error );
						$this->assertSame( '', $value );

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
					function ( OD_Tag_Visitor_Context $context ): void {
						$this->assertFalse( $context->processor->is_tag_closer() );
						if ( $context->processor->get_tag() === 'VIDEO' ) {
							$context->track_tag();
						}
					}
				);
			}
		);

		$this->assert_snapshot_equals( $directory );
	}
}
