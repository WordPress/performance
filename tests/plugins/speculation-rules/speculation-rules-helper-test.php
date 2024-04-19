<?php
/**
 * Tests for speculation-rules helper file.
 *
 * @package speculation-rules
 */

class Speculation_Rules_Helper_Tests extends WP_UnitTestCase {

	/**
	 * @covers ::plsr_get_speculation_rules
	 */
	public function test_plsr_get_speculation_rules() {
		$rules = plsr_get_speculation_rules();

		$this->assertIsArray( $rules );
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
	public function test_plsr_get_speculation_rules_href_exclude_paths() {
		$rules              = plsr_get_speculation_rules();
		$href_exclude_paths = $rules['prerender'][0]['where']['and'][1]['not']['href_matches'];

		$this->assertSameSets(
			array(
				'/wp-login.php',
				'/wp-admin/*',
				'/*\\?*(^|&)_wpnonce=*',
			),
			$href_exclude_paths
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
				'/wp-login.php',
				'/wp-admin/*',
				'/*\\?*(^|&)_wpnonce=*',
				'/custom-file.php',
			),
			$href_exclude_paths
		);
	}

	/**
	 * @covers ::plsr_get_speculation_rules
	 */
	public function test_plsr_get_speculation_rules_href_exclude_paths_with_mode() {
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
				0 => '/wp-login.php',
				1 => '/wp-admin/*',
				2 => '/*\\?*(^|&)_wpnonce=*',
				3 => '/products/*',
			),
			$href_exclude_paths
		);

		// Update mode to be 'prefetch'.
		update_option( 'plsr_speculation_rules', array( 'mode' => 'prefetch' ) );

		$rules              = plsr_get_speculation_rules();
		$href_exclude_paths = $rules['prefetch'][0]['where']['and'][1]['not']['href_matches'];

		// Ensure the additional exclusion is not present because the mode is 'prefetch'.
		$this->assertSame(
			array(
				0 => '/wp-login.php',
				1 => '/wp-admin/*',
				2 => '/*\\?*(^|&)_wpnonce=*',
			),
			$href_exclude_paths
		);
	}


	/**
	 * @covers ::plsr_get_speculation_rules
	 */
	public function test_plsr_get_speculation_rules_with_filtering_bad_keys() {

		// Filter that explicitly adds bogus keys.
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

		// Ensure the additional exclusion is not present because the mode is 'prefetch'.
		$this->assertSame(
			array(
				0 => '/wp-login.php',
				1 => '/wp-admin/*',
				2 => '/*\\?*(^|&)_wpnonce=*',
				3 => '/unshifted/',
				4 => '/next/',
				5 => '/negative-one/',
				6 => '/one-hundred/',
				7 => '/letter-a/',
			),
			plsr_get_speculation_rules()['prerender'][0]['where']['and'][1]['not']['href_matches']
		);
	}

	/**
	 * @covers ::plsr_get_speculation_rules
	 */
	public function test_plsr_get_speculation_rules_different_home_and_site_urls() {
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

		$this->assertSame(
			array(
				0 => '/wp/wp-login.php',
				1 => '/wp/wp-admin/*',
				2 => '/blog/*\\?*(^|&)_wpnonce=*',
				3 => '/blog/store/*',
			),
			plsr_get_speculation_rules()['prerender'][0]['where']['and'][1]['not']['href_matches']
		);
	}

	/**
	 * @covers ::plsr_get_speculation_rules
	 */
	public function test_plsr_get_speculation_rules_prerender() {
		$rules = plsr_get_speculation_rules();

		$this->assertArrayHasKey( 'prerender', $rules );
		$this->assertCount( 4, $rules['prerender'][0]['where']['and'] );
	}

	/**
	 * @covers ::plsr_get_speculation_rules
	 */
	public function test_plsr_get_speculation_rules_prefetch() {
		update_option( 'plsr_speculation_rules', array( 'mode' => 'prefetch' ) );

		$rules = plsr_get_speculation_rules();

		$this->assertArrayHasKey( 'prefetch', $rules );
		$this->assertCount( 3, $rules['prefetch'][0]['where']['and'] );
	}

	/**
	 * @covers ::plsr_get_speculation_rules
	 * @dataProvider data_plsr_get_speculation_rules_with_eagerness
	 */
	public function test_plsr_get_speculation_rules_with_eagerness( string $eagerness ) {
		update_option( 'plsr_speculation_rules', array( 'eagerness' => $eagerness ) );

		$rules = plsr_get_speculation_rules();

		$this->assertArrayHasKey( 'prerender', $rules );
		$this->assertSame( $eagerness, $rules['prerender'][0]['eagerness'] );
	}

	public function data_plsr_get_speculation_rules_with_eagerness() {
		return array(
			array( 'conservative' ),
			array( 'moderate' ),
			array( 'eager' ),
		);
	}
}
