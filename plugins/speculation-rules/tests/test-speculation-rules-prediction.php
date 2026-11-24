<?php
/**
 * Tests for speculation-rules link prediction file.
 *
 * @package speculation-rules
 */

class Test_Speculation_Rules_Prediction extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );
	}

	/**
	 * Enables link prediction.
	 *
	 * @param 'prefetch'|'prerender' $mode Mode.
	 */
	private function enable_link_prediction( string $mode = 'prerender' ): void {
		update_option(
			'plsr_speculation_rules',
			array(
				'mode'       => $mode,
				'prediction' => 'enabled',
			)
		);
	}

	/**
	 * @covers ::plsr_is_link_prediction_enabled
	 */
	public function test_plsr_is_link_prediction_enabled(): void {
		$this->assertFalse( plsr_is_link_prediction_enabled(), 'Expected link prediction to be disabled by default.' );

		$this->enable_link_prediction();
		$this->assertTrue( plsr_is_link_prediction_enabled() );

		// Speculative loading is disabled for logged-in users by default.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertFalse( plsr_is_link_prediction_enabled(), 'Expected link prediction to be disabled when speculative loading is.' );
	}

	/**
	 * @covers ::plsr_get_link_prediction_rule
	 * @dataProvider data_modes
	 *
	 * @param 'prefetch'|'prerender' $mode Mode.
	 */
	public function test_plsr_get_link_prediction_rule( string $mode ): void {
		$this->enable_link_prediction( $mode );
		add_filter(
			'plsr_speculation_rules_href_exclude_paths',
			static function ( array $exclude_paths ): array {
				$exclude_paths[] = '/cart/*';
				return $exclude_paths;
			}
		);

		$rule = plsr_get_link_prediction_rule();
		$this->assertIsArray( $rule );
		$this->assertSame( $mode, $rule['mode'] );
		$this->assertArrayHasKey( 'and', $rule['where'] );

		// The exclusions for speculative loading are part of the conditions.
		$json = (string) wp_json_encode( $rule['where'], JSON_UNESCAPED_SLASHES );
		$this->assertStringContainsString( '/wp-admin/*', $json );
		$this->assertStringContainsString( '/cart/*', $json );
		$this->assertStringContainsString( 'nofollow', $json );
	}

	/**
	 * @return array<string, array{ 0: 'prefetch'|'prerender' }>
	 */
	public function data_modes(): array {
		return array(
			'prefetch'  => array( 'prefetch' ),
			'prerender' => array( 'prerender' ),
		);
	}

	/**
	 * @covers ::plsr_get_link_prediction_rule
	 * @covers ::plsr_get_link_prediction_config
	 */
	public function test_plsr_get_link_prediction_rule_without_speculative_loading(): void {
		add_filter( 'wp_speculation_rules_configuration', '__return_null', 100 );

		$this->assertNull( plsr_get_link_prediction_rule() );
		$this->assertNull( plsr_get_link_prediction_config() );
	}

	/**
	 * @covers ::plsr_get_link_prediction_config
	 */
	public function test_plsr_get_link_prediction_config_defaults(): void {
		$config = plsr_get_link_prediction_config();
		$this->assertIsArray( $config );
		$this->assertSame( 'prerender', $config['mode'] );
		$this->assertArrayHasKey( 'and', $config['where'] );
		$this->assertSame( 0.5, $config['threshold'] );
		$this->assertSame( 2, $config['maxLinks'] );
		$this->assertSame( 0.1, $config['learningRate'] );
		$this->assertSame(
			array( 'bias', 'visible', 'proximity', 'area', 'content', 'navigation', 'footer', 'heading', 'image', 'next', 'repeated' ),
			array_keys( $config['weights'] )
		);
		foreach ( $config['weights'] as $weight ) {
			$this->assertIsFloat( $weight );
		}
	}

	/**
	 * @covers ::plsr_get_link_prediction_config
	 */
	public function test_plsr_get_link_prediction_config_filtered(): void {
		$filtered_mode = null;
		add_filter(
			'plsr_link_prediction_args',
			static function ( array $args, string $mode ) use ( &$filtered_mode ): array {
				$filtered_mode         = $mode;
				$args['threshold']     = 7;
				$args['max_links']     = '5';
				$args['learning_rate'] = -1;
				$args['weights']       = array(
					'next'  => 3,
					'image' => 'bad',
					'bogus' => 1.0,
				);
				return $args;
			},
			10,
			2
		);

		$config = plsr_get_link_prediction_config();
		$this->assertIsArray( $config );
		$this->assertSame( 'prerender', $filtered_mode );
		$this->assertSame( 1.0, $config['threshold'], 'Expected the threshold to be limited to 1.' );
		$this->assertSame( 5, $config['maxLinks'] );
		$this->assertSame( 0.0, $config['learningRate'], 'Expected the learning rate to be limited to 0.' );
		$this->assertSame( 3.0, $config['weights']['next'] );
		$this->assertSame( 0.3, $config['weights']['image'], 'Expected an invalid weight to keep its default.' );
		$this->assertSame( -3.0, $config['weights']['bias'], 'Expected an omitted weight to keep its default.' );
		$this->assertArrayNotHasKey( 'bogus', $config['weights'] );
	}

	/**
	 * @covers ::plsr_get_link_prediction_config
	 */
	public function test_plsr_get_link_prediction_config_with_invalid_filter_return(): void {
		add_filter( 'plsr_link_prediction_args', '__return_false' );

		$config = plsr_get_link_prediction_config();
		$this->assertIsArray( $config );
		$this->assertSame( 0.5, $config['threshold'] );
		$this->assertSame( 2, $config['maxLinks'] );
	}

	/**
	 * @covers ::plsr_print_link_prediction_script
	 */
	public function test_plsr_print_link_prediction_script_hook(): void {
		$this->assertSame( 11, has_action( 'wp_footer', 'plsr_print_link_prediction_script' ) );
	}

	/**
	 * @covers ::plsr_print_link_prediction_script
	 */
	public function test_plsr_print_link_prediction_script_disabled(): void {
		$this->assertSame( '', get_echo( 'plsr_print_link_prediction_script' ) );
	}

	/**
	 * @covers ::plsr_print_link_prediction_script
	 */
	public function test_plsr_print_link_prediction_script_without_links(): void {
		$this->enable_link_prediction();
		add_filter(
			'plsr_link_prediction_args',
			static function ( array $args ): array {
				$args['max_links'] = 0;
				return $args;
			}
		);

		$this->assertSame( '', get_echo( 'plsr_print_link_prediction_script' ) );
	}

	/**
	 * @covers ::plsr_print_link_prediction_script
	 * @covers ::plsr_get_asset_path
	 */
	public function test_plsr_print_link_prediction_script(): void {
		$this->enable_link_prediction( 'prefetch' );

		$output = get_echo( 'plsr_print_link_prediction_script' );

		$p = new WP_HTML_Tag_Processor( $output );
		$this->assertTrue( $p->next_tag( array( 'tag_name' => 'SCRIPT' ) ) );
		$this->assertSame( 'application/json', $p->get_attribute( 'type' ) );
		$this->assertSame( 'plsr-link-prediction-config', $p->get_attribute( 'id' ) );
		$config = json_decode( $p->get_modifiable_text(), true );
		$this->assertIsArray( $config );
		$this->assertSame( 'prefetch', $config['mode'] );
		$this->assertSame(
			array( 'mode', 'where', 'threshold', 'maxLinks', 'learningRate', 'weights' ),
			array_keys( $config )
		);

		$this->assertTrue( $p->next_tag( array( 'tag_name' => 'SCRIPT' ) ) );
		$this->assertSame( 'module', $p->get_attribute( 'type' ) );
		$script = $p->get_modifiable_text();
		$this->assertStringContainsString( 'plsr-link-prediction-config', $script );
		$this->assertStringContainsString( 'speculationrules', $script );
		$this->assertStringContainsString( '//# sourceURL=speculation-rules-link-prediction', $script );
	}
}
