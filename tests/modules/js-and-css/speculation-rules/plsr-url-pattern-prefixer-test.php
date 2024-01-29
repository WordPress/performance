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
			// A base path containing a : must be enclosed in braces to avoid confusion.
			array( '/scope:0/', '/*/foo', '{/scope\\:0}/*/foo' ),
		);
	}

	public function test_get_default_contexts() {
		$contexts = PLSR_URL_Pattern_Prefixer::get_default_contexts();

		$this->assertArrayHasKey( 'home', $contexts );
		$this->assertArrayHasKey( 'site', $contexts );
		$this->assertSame( '/', $contexts['home'] );
		$this->assertSame( '/', $contexts['site'] );
	}

	/**
	 * @dataProvider data_default_contexts_with_subdirectories
	 */
	public function test_get_default_contexts_with_subdirectories( $context, $unescaped, $expected ) {
		add_filter(
			$context . '_url',
			static function () use ( $unescaped ) {
				return $unescaped;
			}
		);

		$contexts = PLSR_URL_Pattern_Prefixer::get_default_contexts();

		$this->assertArrayHasKey( $context, $contexts );
		$this->assertSame( $expected, $contexts[ $context ] );
	}

	public function data_default_contexts_with_subdirectories() {
		return array(
			array( 'home', 'https://example.com/subdir/', '/subdir/' ),
			array( 'site', 'https://example.com/subdir/wp/', '/subdir/wp/' ),
			// If the context URL has URL pattern special characters it may need escaping.
			array( 'home', 'https://example.com/scope:0.*/', '/scope\\:0.\\*/' ),
			array( 'site', 'https://example.com/scope:0.*/wp+/', '/scope\\:0.\\*/wp\\+/' ),
		);
	}
}
