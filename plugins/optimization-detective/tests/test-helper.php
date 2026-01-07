<?php
/**
 * Tests for optimization-detective plugin helper.php.
 *
 * @package optimization-detective
 */

class Test_OD_Helper extends WP_UnitTestCase {

	/**
	 * @covers ::od_initialize_extensions
	 */
	public function test_od_initialize_extensions(): void {
		unset( $GLOBALS['wp_actions']['od_init'] );
		$passed_version = null;
		add_action(
			'od_init',
			static function ( string $version ) use ( &$passed_version ): void {
				$passed_version = $version;
			}
		);
		od_initialize_extensions();
		$this->assertSame( 1, did_action( 'od_init' ) );
		$this->assertSame( OPTIMIZATION_DETECTIVE_VERSION, $passed_version );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function data_to_test_od_generate_media_query(): array {
		return array(
			'mobile'      => array(
				'min_width' => 0,
				'max_width' => 320,
				'expected'  => '(width <= 320px)',
			),
			'mobile_alt'  => array(
				'min_width' => null,
				'max_width' => 320,
				'expected'  => '(width <= 320px)',
			),
			'tablet'      => array(
				'min_width' => 320,
				'max_width' => 600,
				'expected'  => '(320px < width <= 600px)',
			),
			'desktop'     => array(
				'min_width' => 600,
				'max_width' => PHP_INT_MAX,
				'expected'  => '(600px < width)',
			),
			'desktop_alt' => array(
				'min_width' => 600,
				'max_width' => null,
				'expected'  => '(600px < width)',
			),
			'no_widths'   => array(
				'min_width' => null,
				'max_width' => null,
				'expected'  => null,
			),
			'bad_widths'  => array(
				'min_width'       => 1000,
				'max_width'       => 10,
				'expected'        => null,
				'incorrect_usage' => 'od_generate_media_query',
			),
		);
	}

	/**
	 * Tests generating media query.
	 *
	 * @dataProvider data_to_test_od_generate_media_query
	 * @covers ::od_generate_media_query
	 */
	public function test_od_generate_media_query( ?int $min_width, ?int $max_width, ?string $expected, ?string $incorrect_usage = null ): void {
		if ( null !== $incorrect_usage ) {
			$this->setExpectedIncorrectUsage( $incorrect_usage );
		}
		$this->assertSame( $expected, od_generate_media_query( $min_width, $max_width ) );
	}

	/**
	 * Tests printing the META generator tag.
	 *
	 * @covers ::od_render_generator_meta_tag
	 */
	public function test_od_render_generator_meta_tag(): void {
		$tag = get_echo( 'od_render_generator_meta_tag' );
		$this->assertStringStartsWith( '<meta', $tag );
		$this->assertStringContainsString( 'generator', $tag );
		$this->assertStringContainsString( 'optimization-detective ' . OPTIMIZATION_DETECTIVE_VERSION, $tag );
		$this->assertFalse( od_is_rest_api_unavailable() );
		$this->assertStringNotContainsString( 'rest_api_unavailable', $tag );
	}

	/**
	 * Tests META generator tag when query parameter is present.
	 *
	 * @covers ::od_render_generator_meta_tag
	 * @covers ::od_get_disabled_reasons
	 */
	public function test_od_render_generator_meta_tag_query_param_disabled(): void {
		$_GET['optimization_detective_disabled'] = '1';
		$tag                                     = get_echo( 'od_render_generator_meta_tag' );
		$this->assertStringContainsString( '; query_param_disabled', $tag );
		unset( $_GET['optimization_detective_disabled'] );
	}

	/**
	 * Tests printing the META generator tag when the REST API is not available.
	 *
	 * @covers ::od_render_generator_meta_tag
	 * @covers ::od_get_disabled_reasons
	 */
	public function test_od_render_generator_meta_tag_rest_api_unavailable(): void {
		update_option( 'od_rest_api_unavailable', '1' );
		$tag = get_echo( 'od_render_generator_meta_tag' );
		$this->assertStringStartsWith( '<meta', $tag );
		$this->assertStringContainsString( 'generator', $tag );
		$this->assertStringContainsString( 'optimization-detective ' . OPTIMIZATION_DETECTIVE_VERSION, $tag );
		$this->assertTrue( od_is_rest_api_unavailable() );
		$this->assertStringContainsString( '; rest_api_unavailable', $tag );
	}

	/**
	 * Tests rendering extensions action link.
	 *
	 * @covers ::od_render_extensions_action_link
	 */
	public function test_od_render_extensions_action_link(): void {
		$input  = array(
			'deactivate' => '<a href="#">Deactivate</a>',
			'edit'       => '<a href="#">Edit</a>',
		);
		$result = od_render_extensions_action_link( $input );

		$this->assertArrayHasKey( 'extensions', $result );
		$this->assertArrayHasKey( 'deactivate', $result );
		$this->assertArrayHasKey( 'edit', $result );
		$this->assertStringContainsString( 'plugin-install.php?s=optimization-detective', $result['extensions'] );
		$this->assertStringContainsString( 'Extensions', $result['extensions'] );
		$this->assertSame( '<a href="#">Deactivate</a>', $result['deactivate'] );
		$this->assertSame( '<a href="#">Edit</a>', $result['edit'] );

		// Check that it's first in the array.
		$keys = array_keys( $result );
		$this->assertSame( 'extensions', $keys[0] );
	}

	/**
	 * Tests rendering extensions action link with non-array input.
	 *
	 * @covers ::od_render_extensions_action_link
	 */
	public function test_od_render_extensions_action_link_non_array(): void {
		$result = od_render_extensions_action_link( 'not an array' );

		$this->assertArrayHasKey( 'extensions', $result );
		$this->assertStringContainsString( 'Extensions', $result['extensions'] );
	}

	/**
	 * Tests checking installed and active extensions.
	 *
	 * @covers ::od_get_active_extensions
	 */
	public function test_od_get_active_extensions(): void {
		$installed_extensions = od_get_active_extensions();
		// Extensions are installed but not active in the test environment.
		$this->assertSame( array(), $installed_extensions );

		activate_plugins( array( 'optimization-detective/load.php', 'image-prioritizer/load.php', 'embed-optimizer/load.php' ) );
		$installed_extensions = od_get_active_extensions();
		$this->assertSame(
			array(
				'image-prioritizer/load.php',
				'embed-optimizer/load.php',
			),
			$installed_extensions
		);
	}

	/**
	 * Tests rendering installed extensions admin notice with various scenarios.
	 *
	 * @covers ::od_maybe_render_installed_extensions_admin_notice
	 * @covers ::od_get_active_extensions
	 */
	public function test_od_maybe_render_installed_extensions_admin_notice(): void {
		// Without capability, no output.
		$output = get_echo( 'od_maybe_render_installed_extensions_admin_notice' );
		$this->assertSame( '', $output );

		// With capability and no active extensions, notice is shown.
		$user = self::factory()->user->create();
		wp_set_current_user( $user );
		if ( is_multisite() ) {
			grant_super_admin( $user );
		} else {
			$current_user = wp_get_current_user();
			$current_user->add_cap( 'activate_plugins' );
		}

		$output = get_echo( 'od_maybe_render_installed_extensions_admin_notice' );
		$this->assertStringContainsString( '<div class="notice notice-info', $output );
		$this->assertStringContainsString( '<details>', $output );
		$this->assertStringContainsString( '</summary>', $output );

		// With capability and active extensions, no notice.
		activate_plugins( array( 'optimization-detective/load.php', 'image-prioritizer/load.php', 'embed-optimizer/load.php' ) );

		$output = get_echo( 'od_maybe_render_installed_extensions_admin_notice' );
		$this->assertSame( '', $output );
	}

	/**
	 * Tests od_render_documentation_links().
	 *
	 * @covers ::od_render_documentation_links
	 */
	public function test_od_render_documentation_links(): void {
		$processor = new WP_HTML_Tag_Processor( get_echo( 'od_render_documentation_links' ) );
		$this->assertTrue( $processor->next_tag( 'P' ), 'Expected P to be the first tag.' );
		$found_links = 0;
		while ( $processor->next_tag() ) {
			$this->assertSame( 'A', $processor->get_tag(), 'Expected tag in paragraph to be a link.' );
			$href = $processor->get_attribute( 'href' );
			$this->assertIsString( $href, 'Expected A to be the second tag.' );
			$this->assertSame( 'github.com', wp_parse_url( $href, PHP_URL_HOST ) );
			$this->assertSame( '_blank', $processor->get_attribute( 'target' ) );
			++$found_links;
		}
		$this->assertSame( 4, $found_links, 'Expected there to be 4 links.' );
	}

	/**
	 * Tests od_render_installed_extensions_admin_notice_in_plugin_row with various scenarios.
	 *
	 * @covers ::od_render_installed_extensions_admin_notice_in_plugin_row
	 * @covers ::od_maybe_render_installed_extensions_admin_notice
	 * @covers ::od_render_documentation_links
	 */
	public function test_od_render_installed_extensions_admin_notice_in_plugin_row(): void {
		$user = self::factory()->user->create();
		wp_set_current_user( $user );
		if ( is_multisite() ) {
			grant_super_admin( $user );
		} else {
			$current_user = wp_get_current_user();
			$current_user->add_cap( 'activate_plugins' );
		}

		// When called for a different plugin, no output.
		$this->assertSame( '', get_echo( 'od_render_installed_extensions_admin_notice_in_plugin_row', array( 'foo.php' ) ) );

		// With no active extensions, notice is shown.
		$notice = get_echo( 'od_render_installed_extensions_admin_notice_in_plugin_row', array( 'optimization-detective/load.php' ) );

		$processor = new WP_HTML_Tag_Processor( $notice );
		$this->assertTrue( $processor->next_tag( 'DIV' ), 'Expected DIV to be present.' );
		$this->assertTrue( $processor->has_class( 'notice' ), 'Expected DIV to have a "notice" class.' );
		$this->assertTrue( $processor->has_class( 'notice-info' ), 'Expected DIV to have a "notice-info" class.' );
		$this->assertTrue( $processor->next_tag( 'DETAILS' ), 'Expected DETAILS to be present.' );
		$this->assertTrue( $processor->next_tag( 'SUMMARY' ), 'Expected SUMMARY to be present.' );

		$found_github_link = false;
		while ( $processor->next_tag( 'A' ) ) {
			$href = $processor->get_attribute( 'href' );
			if ( is_string( $href ) && 'github.com' === wp_parse_url( $href, PHP_URL_HOST ) ) {
				$found_github_link = true;
				break;
			}
		}
		$this->assertTrue( $found_github_link, 'Expected there to be a link to GitHub.' );
	}
}
