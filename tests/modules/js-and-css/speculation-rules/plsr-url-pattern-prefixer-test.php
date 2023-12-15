<?php
/**
 * Tests for PLSR_URL_Pattern_Prefixer class.
 *
 * @package performance-lab
 * @group speculation-rules
 */

class PLSR_URL_Pattern_Prefixer_Tests extends WP_UnitTestCase {

	/**
	 * @dataProvider data_prefix_path_pattern
	 */
	public function test_prefix_path_pattern( $base_path, $path_pattern, $expected ) {
		$p = new PLSR_URL_Pattern_Prefixer( array( 'demo' => $base_path ) );

		$this->assertSame(
			$expected,
			$p->prefix_path_pattern( $path_pattern, 'demo' )
		);
	}

	public function data_prefix_path_pattern() {
		return array(
			array( '/', '/my-page/', '/my-page/' ),
			array( '/', 'my-page/', '/my-page/' ),
			array( '/wp/', '/my-page/', '/wp/my-page/' ),
			array( '/wp/', 'my-page/', '/wp/my-page/' ),
			array( '/wp/', '/blog/2023/11/new-post/', '/wp/blog/2023/11/new-post/' ),
			array( '/wp/', 'blog/2023/11/new-post/', '/wp/blog/2023/11/new-post/' ),
			array( '/subdir', '/my-page/', '/subdir/my-page/' ),
			array( '/subdir', 'my-page/', '/subdir/my-page/' ),
			// Missing trailing slash still works, does not consider "cut-off" directory names.
			array( '/subdir', '/subdirectory/my-page/', '/subdir/subdirectory/my-page/' ),
			array( '/subdir', 'subdirectory/my-page/', '/subdir/subdirectory/my-page/' ),
		);
	}

	public function test_get_default_contexts() {
		$contexts = PLSR_URL_Pattern_Prefixer::get_default_contexts();

		$this->assertArrayHasKey( 'home', $contexts );
		$this->assertArrayHasKey( 'site', $contexts );
		$this->assertSame( '/', $contexts['home'] );
		$this->assertSame( '/', $contexts['site'] );
	}

	public function test_get_default_contexts_with_subdirectories() {
		add_filter(
			'home_url',
			static function () {
				return 'https://example.com/subdir/';
			}
		);
		add_filter(
			'site_url',
			static function () {
				return 'https://example.com/subdir/wp/';
			}
		);

		$contexts = PLSR_URL_Pattern_Prefixer::get_default_contexts();

		$this->assertArrayHasKey( 'home', $contexts );
		$this->assertArrayHasKey( 'site', $contexts );
		$this->assertSame( '/subdir/', $contexts['home'] );
		$this->assertSame( '/subdir/wp/', $contexts['site'] );
	}
}
