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
			function ( $exclude_paths, $mode ) {
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
		$this->assertContains( '/products/*', $href_exclude_paths );

		// Update mode to be 'prefetch'.
		update_option( 'plsr_speculation_rules', array( 'mode' => 'prefetch' ) );

		$rules              = plsr_get_speculation_rules();
		$href_exclude_paths = $rules['prefetch'][0]['where']['and'][1]['not']['href_matches'];

		// Ensure the additional exclusion is not present because the mode is 'prefetch'.
		$this->assertNotContains( '/products/*', $href_exclude_paths );
	}

	/**
	 * @covers ::plsr_get_speculation_rules
	 */
	public function test_plsr_get_speculation_rules_prerender() {
		$rules = plsr_get_speculation_rules();

		$this->assertArrayHasKey( 'prerender', $rules );
		$this->assertCount( 3, $rules['prerender'][0]['where']['and'] );
	}

	/**
	 * @covers ::plsr_get_speculation_rules
	 */
	public function test_plsr_get_speculation_rules_prefetch() {
		update_option( 'plsr_speculation_rules', array( 'mode' => 'prefetch' ) );

		$rules = plsr_get_speculation_rules();

		$this->assertArrayHasKey( 'prefetch', $rules );
		$this->assertCount( 2, $rules['prefetch'][0]['where']['and'] );
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
