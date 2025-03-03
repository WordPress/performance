<?php
/**
 * Tests for speculation-rules plugin API.
 *
 * @package speculation-rules
 */

class Test_Speculation_Rules_Plugin_API extends WP_UnitTestCase {

	/** @var array<string, mixed> */
	private $original_wp_theme_features = array();

	/**
	 * Runs the routine before each test is executed.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->original_wp_theme_features = $GLOBALS['_wp_theme_features'];

		add_filter(
			'template_directory_uri',
			static function () {
				return content_url( 'themes/template' );
			}
		);

		add_filter(
			'stylesheet_directory_uri',
			static function () {
				return content_url( 'themes/stylesheet' );
			}
		);
	}

	public function tear_down(): void {
		$GLOBALS['_wp_theme_features'] = $this->original_wp_theme_features;
		parent::tear_down();
	}

	/**
	 * @covers ::plsr_get_speculation_rules
	 */
	public function test_plsr_get_speculation_rules(): void {
		$rules = plsr_get_speculation_rules();

		$this->assertArrayHasKey( 'prerender', $rules );
		$this->assertIsArray( $rules['prerender'] );
		foreach ( $rules['prerender'] as $entry ) {
			$this->assertIsArray( $entry );
			$this->assertArrayHasKey( 'source', $entry );
			$this->assertTrue( in_array( $entry['source'], array( 'list', 'document' ), true ) );
		}
	}

	/**
	 * @covers ::plsr_get_speculation_rules
	 */
	public function test_plsr_get_speculation_rules_href_exclude_paths(): void {
		$rules              = plsr_get_speculation_rules();
		$href_exclude_paths = $rules['prerender'][0]['where']['and'][1]['not']['href_matches'];

		$this->assertSameSets(
			array(
				'/wp-*.php',
				'/wp-admin/*',
				'/wp-content/uploads/*',
				'/wp-content/*',
				'/wp-content/plugins/*',
				'/wp-content/themes/stylesheet/*',
				'/wp-content/themes/template/*',
				'/*\\?*(^|&)_wpnonce=*',
			),
			$href_exclude_paths,
			'Snapshot: ' . var_export( $href_exclude_paths, true )
		);

		// Add filter that attempts to replace base exclude paths with a custom path to exclude.
		add_filter(
			'plsr_speculation_rules_href_exclude_paths',
			static function () {
				return array( 'custom-file.php' );
			}
		);

		$rules              = plsr_get_speculation_rules();
		$href_exclude_paths = $rules['prerender'][0]['where']['and'][1]['not']['href_matches'];

		// Ensure the base exclude paths are still present and that the custom path was formatted correctly.
		$this->assertSameSets(
			array(
				'/wp-*.php',
				'/wp-admin/*',
				'/wp-content/uploads/*',
				'/wp-content/*',
				'/wp-content/plugins/*',
				'/wp-content/themes/stylesheet/*',
				'/wp-content/themes/template/*',
				'/*\\?*(^|&)_wpnonce=*',
				'/custom-file.php',
			),
			$href_exclude_paths,
			'Snapshot: ' . var_export( $href_exclude_paths, true )
		);
	}

	/**
	 * @covers ::plsr_get_speculation_rules
	 */
	public function test_plsr_get_speculation_rules_href_exclude_paths_with_pretty_permalinks(): void {
		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );

		$rules              = plsr_get_speculation_rules();
		$href_exclude_paths = $rules['prerender'][0]['where']['and'][1]['not']['href_matches'];

		$this->assertSameSets(
			array(
				'/wp-*.php',
				'/wp-admin/*',
				'/wp-content/uploads/*',
				'/wp-content/*',
				'/wp-content/plugins/*',
				'/wp-content/themes/stylesheet/*',
				'/wp-content/themes/template/*',
				'/*\\?(.+)',
			),
			$href_exclude_paths,
			'Snapshot: ' . var_export( $href_exclude_paths, true )
		);
	}

	/**
	 * @covers ::plsr_get_speculation_rules
	 */
	public function test_plsr_get_speculation_rules_href_exclude_paths_with_mode(): void {
		// Add filter that adds an exclusion only if the mode is 'prerender'.
		add_filter(
			'plsr_speculation_rules_href_exclude_paths',
			static function ( $exclude_paths, $mode ) {
				if ( 'prerender' === $mode ) {
					$exclude_paths[] = '/products/*';
				}
				return $exclude_paths;
			},
			10,
			2
		);

		$rules              = plsr_get_speculation_rules();
		$href_exclude_paths = $rules['prerender'][0]['where']['and'][1]['not']['href_matches'];

		// Ensure the additional exclusion is present because the mode is 'prerender'.
		// Also ensure keys are sequential starting from 0 (that is, that array_is_list()).
		$this->assertSame(
			array(
				'/wp-*.php',
				'/wp-admin/*',
				'/wp-content/uploads/*',
				'/wp-content/*',
				'/wp-content/plugins/*',
				'/wp-content/themes/stylesheet/*',
				'/wp-content/themes/template/*',
				'/*\\?*(^|&)_wpnonce=*',
				'/products/*',
			),
			$href_exclude_paths,
			'Snapshot: ' . var_export( $href_exclude_paths, true )
		);

		// Update mode to be 'prefetch'.
		update_option( 'plsr_speculation_rules', array( 'mode' => 'prefetch' ) );

		$rules              = plsr_get_speculation_rules();
		$href_exclude_paths = $rules['prefetch'][0]['where']['and'][1]['not']['href_matches'];

		// Ensure the additional exclusion is not present because the mode is 'prefetch'.
		$this->assertSame(
			array(
				'/wp-*.php',
				'/wp-admin/*',
				'/wp-content/uploads/*',
				'/wp-content/*',
				'/wp-content/plugins/*',
				'/wp-content/themes/stylesheet/*',
				'/wp-content/themes/template/*',
				'/*\\?*(^|&)_wpnonce=*',
			),
			$href_exclude_paths,
			'Snapshot: ' . var_export( $href_exclude_paths, true )
		);
	}

	/**
	 * Tests filter that explicitly adds non-sequential keys.
	 *
	 * @covers ::plsr_get_speculation_rules
	 */
	public function test_plsr_get_speculation_rules_with_filtering_bad_keys(): void {

		add_filter(
			'plsr_speculation_rules_href_exclude_paths',
			static function ( array $exclude_paths ): array {
				$exclude_paths[] = '/next/';
				array_unshift( $exclude_paths, '/unshifted/' );
				$exclude_paths[-1]  = '/negative-one/';
				$exclude_paths[100] = '/one-hundred/';
				$exclude_paths['a'] = '/letter-a/';
				return $exclude_paths;
			}
		);

		$actual = plsr_get_speculation_rules()['prerender'][0]['where']['and'][1]['not']['href_matches'];
		$this->assertSame(
			array(
				'/wp-*.php',
				'/wp-admin/*',
				'/wp-content/uploads/*',
				'/wp-content/*',
				'/wp-content/plugins/*',
				'/wp-content/themes/stylesheet/*',
				'/wp-content/themes/template/*',
				'/*\\?*(^|&)_wpnonce=*',
				'/unshifted/',
				'/next/',
				'/negative-one/',
				'/one-hundred/',
				'/letter-a/',
			),
			$actual,
			'Snapshot: ' . var_export( $actual, true )
		);
	}

	/**
	 * Tests scenario when the home_url and site_url have different paths.
	 *
	 * @covers ::plsr_get_speculation_rules
	 */
	public function test_plsr_get_speculation_rules_different_home_and_site_urls(): void {
		add_filter(
			'site_url',
			static function (): string {
				return 'https://example.com/wp/';
			}
		);
		add_filter(
			'home_url',
			static function (): string {
				return 'https://example.com/blog/';
			}
		);
		add_filter(
			'plsr_speculation_rules_href_exclude_paths',
			static function ( array $exclude_paths ): array {
				$exclude_paths[] = '/store/*';
				return $exclude_paths;
			}
		);

		$actual = plsr_get_speculation_rules()['prerender'][0]['where']['and'][1]['not']['href_matches'];
		$this->assertSame(
			array(
				'/wp/wp-*.php',
				'/wp/wp-admin/*',
				'/wp-content/uploads/*',
				'/wp-content/*',
				'/wp-content/plugins/*',
				'/wp-content/themes/stylesheet/*',
				'/wp-content/themes/template/*',
				'/blog/*\\?*(^|&)_wpnonce=*',
				'/blog/store/*',
			),
			$actual,
			'Snapshot: ' . var_export( $actual, true )
		);
	}

	/**
	 * @covers ::plsr_get_speculation_rules
	 */
	public function test_plsr_get_speculation_rules_prerender(): void {
		$rules = plsr_get_speculation_rules();

		$this->assertArrayHasKey( 'prerender', $rules );
		$this->assertCount( 4, $rules['prerender'][0]['where']['and'] );
	}

	/**
	 * @covers ::plsr_get_speculation_rules
	 */
	public function test_plsr_get_speculation_rules_prefetch(): void {
		update_option( 'plsr_speculation_rules', array( 'mode' => 'prefetch' ) );

		$rules = plsr_get_speculation_rules();

		$this->assertArrayHasKey( 'prefetch', $rules );
		$this->assertCount( 3, $rules['prefetch'][0]['where']['and'] );
	}

	/**
	 * @covers ::plsr_get_speculation_rules
	 * @dataProvider data_plsr_get_speculation_rules_with_eagerness
	 */
	public function test_plsr_get_speculation_rules_with_eagerness( string $eagerness ): void {
		update_option( 'plsr_speculation_rules', array( 'eagerness' => $eagerness ) );

		$rules = plsr_get_speculation_rules();

		$this->assertArrayHasKey( 'prerender', $rules );
		$this->assertSame( $eagerness, $rules['prerender'][0]['eagerness'] );
	}

	/** @return array<int, string[]> */
	public function data_plsr_get_speculation_rules_with_eagerness(): array {
		return array(
			array( 'conservative' ),
			array( 'moderate' ),
			array( 'eager' ),
		);
	}

	/**
	 * @covers ::plsr_print_speculation_rules
	 */
	public function test_plsr_print_speculation_rules_without_html5_support(): void {
		$this->enable_pretty_permalinks();

		$output = get_echo( 'plsr_print_speculation_rules' );
		$this->assertStringContainsString( '<script type="speculationrules">', $output );

		$json  = str_replace( array( '<script type="speculationrules">', '</script>' ), '', $output );
		$rules = json_decode( $json, true );
		$this->assertIsArray( $rules );
		$this->assertArrayHasKey( 'prerender', $rules );
	}

	/**
	 * @covers ::plsr_print_speculation_rules
	 */
	public function test_plsr_print_speculation_rules_without_pretty_permalinks(): void {
		$this->disable_pretty_permalinks();

		$output = get_echo( 'plsr_print_speculation_rules' );
		$this->assertSame( '', $output );
	}

	/**
	 * @covers ::plsr_print_speculation_rules
	 */
	public function test_plsr_print_speculation_rules_without_pretty_permalinks_but_opted_in(): void {
		$this->disable_pretty_permalinks();
		add_filter( 'plsr_enabled_without_pretty_permalinks', '__return_true' );

		$output = get_echo( 'plsr_print_speculation_rules' );
		$this->assertStringContainsString( '<script type="speculationrules">', $output );
	}

	/**
	 * @covers ::plsr_print_speculation_rules
	 */
	public function test_plsr_print_speculation_rules_for_logged_in_user(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->enable_pretty_permalinks();

		$output = get_echo( 'plsr_print_speculation_rules' );
		$this->assertSame( '', $output );
	}

	private function enable_pretty_permalinks(): void {
		update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );
	}

	private function disable_pretty_permalinks(): void {
		update_option( 'permalink_structure', '' );
	}
}
