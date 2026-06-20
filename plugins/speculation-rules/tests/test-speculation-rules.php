<?php
/**
 * Tests for speculation-rules plugin.
 *
 * @package speculation-rules
 */

class Test_Speculation_Rules extends WP_UnitTestCase {

	/**
	 * Data provider for test_plsr_is_speculative_loading_enabled.
	 *
	 * @return array<string, mixed>
	 */
	public function data_provider_to_test_plsr_is_speculative_loading_enabled(): array {
		return array(
			'default_settings_no_auth_with_pretty_permalinks'    => array(
				'settings'          => null,
				'pretty_permalinks' => true,
				'current_user_role' => null,
				'set_up'            => null,
				'expected'          => true,
			),
			'default_settings_no_auth_without_pretty_permalinks' => array(
				'settings'          => null,
				'pretty_permalinks' => false,
				'current_user_role' => null,
				'set_up'            => null,
				'expected'          => false,
			),
			'default_settings_no_auth_with_pretty_permalinks_filtered_on' => array(
				'settings'          => null,
				'pretty_permalinks' => false,
				'current_user_role' => null,
				'set_up'            => static function (): void {
					add_filter( 'plsr_enabled_without_pretty_permalinks', '__return_true' );
				},
				'expected'          => true,
			),
			'default_settings_auth_admin_auth_with_pretty_permalinks' => array(
				'settings'          => null,
				'pretty_permalinks' => true,
				'current_user_role' => 'administrator',
				'set_up'            => null,
				'expected'          => false,
			),
			'default_settings_auth_subscriber_auth_with_pretty_permalinks' => array(
				'settings'          => null,
				'pretty_permalinks' => true,
				'current_user_role' => 'subscriber',
				'set_up'            => null,
				'expected'          => false,
			),
			'authentication_any_auth_subscriber_auth_with_pretty_permalinks' => array(
				'settings'          => array(
					'authentication' => 'any',
				),
				'pretty_permalinks' => true,
				'current_user_role' => 'subscriber',
				'set_up'            => null,
				'expected'          => true,
			),
			'authentication_logged_out_and_admins_auth_subscriber_auth_with_pretty_permalinks' => array(
				'settings'          => array(
					'authentication' => 'logged_out_and_admins',
				),
				'pretty_permalinks' => true,
				'current_user_role' => 'subscriber',
				'set_up'            => null,
				'expected'          => false,
			),
			'authentication_logged_out_and_admins_auth_administrator_auth_with_pretty_permalinks' => array(
				'settings'          => array(
					'authentication' => 'logged_out_and_admins',
				),
				'pretty_permalinks' => true,
				'current_user_role' => 'administrator',
				'set_up'            => null,
				'expected'          => true,
			),
		);
	}

	/**
	 * @covers ::plsr_is_speculative_loading_enabled
	 * @covers ::plsr_print_speculation_rules
	 *
	 * @dataProvider data_provider_to_test_plsr_is_speculative_loading_enabled
	 *
	 * @phpstan-param array<string, mixed> $settings
	 */
	public function test_plsr_is_speculative_loading_enabled( ?array $settings, bool $pretty_permalinks, ?string $current_user_role, ?Closure $set_up, bool $expected ): void {
		if ( $pretty_permalinks ) {
			update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%hour%/%minute%/%second%' );
		} else {
			update_option( 'permalink_structure', '' );
		}
		if ( null !== $settings ) {
			update_option( 'plsr_speculation_rules', plsr_sanitize_setting( $settings ) );
		}

		if ( null !== $current_user_role ) {
			$user_id = self::factory()->user->create( array( 'role' => $current_user_role ) );
			wp_set_current_user( $user_id );
		}
		if ( $set_up instanceof Closure ) {
			$set_up();
		}
		$this->assertSame( $expected, plsr_is_speculative_loading_enabled() );

		$output = get_echo( function_exists( 'wp_print_speculation_rules' ) ? 'wp_print_speculation_rules' : 'plsr_print_speculation_rules' );
		if ( $expected ) {
			$this->assertNotSame( '', $output );
			$p = new WP_HTML_Tag_Processor( $output );
			$this->assertTrue( $p->next_tag( array( 'tag_name' => 'SCRIPT' ) ) );
			$this->assertSame( 'speculationrules', $p->get_attribute( 'type' ) );
		} else {
			$this->assertSame( '', $output );
		}
	}

	public function test_hooks(): void {
		if ( function_exists( 'wp_get_speculation_rules_configuration' ) ) {
			$this->assertSame( 10, has_filter( 'wp_speculation_rules_configuration', 'plsr_filter_speculation_rules_configuration' ) );
			$this->assertSame( 10, has_filter( 'wp_speculation_rules_href_exclude_paths', 'plsr_filter_speculation_rules_exclude_paths' ) );
		} else {
			$this->assertSame( 10, has_action( 'wp_footer', 'plsr_print_speculation_rules' ) );
		}

		$this->assertSame( 10, has_action( 'wp_head', 'plsr_render_generator_meta_tag' ) );
	}

	/**
	 * Test printing the meta generator tag.
	 *
	 * @covers ::plsr_render_generator_meta_tag
	 */
	public function test_plsr_render_generator_meta_tag(): void {
		$tag = get_echo( 'plsr_render_generator_meta_tag' );
		$this->assertStringStartsWith( '<meta', $tag );
		$this->assertStringContainsString( 'generator', $tag );
		$this->assertStringContainsString( 'speculation-rules ' . SPECULATION_RULES_VERSION, $tag );
	}
}
