<?php
/**
 * Tests for speculation-rules module.
 *
 * @package performance-lab
 * @group speculation-rules
 */

class Speculation_Rules_Tests extends WP_UnitTestCase {

	private $original_wp_theme_features = array();

	public function set_up() {
		parent::set_up();
		$this->original_wp_theme_features = $GLOBALS['_wp_theme_features'];
	}

	public function tear_down() {
		$GLOBALS['_wp_theme_features'] = $this->original_wp_theme_features;
		parent::tear_down();
	}

	public function data_provider_to_test_print_speculation_rules(): array {
		return array(
			'xhtml' => array(
				'html5_support' => false,
			),
			'html5' => array(
				'html5_support' => true,
			),
		);
	}

	/**
	 * @dataProvider data_provider_to_test_print_speculation_rules
	 * @covers ::plsr_print_speculation_rules
	 */
	public function test_plsr_print_speculation_rules_without_html5_support( bool $html5_support ) {
		if ( $html5_support ) {
			add_theme_support( 'html5', array( 'script' ) );
		} else {
			remove_theme_support( 'html5' );
		}

		$output = get_echo( 'plsr_print_speculation_rules' );
		$this->assertStringContainsString( '<script type="speculationrules">', $output );

		$json  = str_replace( array( '<script type="speculationrules">', '</script>' ), '', $output );
		$rules = json_decode( $json, true );
		$this->assertIsArray( $rules );
		$this->assertArrayHasKey( 'prerender', $rules );

		// Make sure that theme support was restored. This is only relevant to WordPres 6.4 per https://core.trac.wordpress.org/ticket/60320.
		if ( $html5_support || version_compare( strtok( get_bloginfo( 'version' ), '-' ), '6.4', '<' ) ) {
			$this->assertStringNotContainsString( '/* <![CDATA[ */', wp_get_inline_script_tag( '/*...*/' ) );
		} else {
			$this->assertStringContainsString( '/* <![CDATA[ */', wp_get_inline_script_tag( '/*...*/' ) );
		}
	}
}
