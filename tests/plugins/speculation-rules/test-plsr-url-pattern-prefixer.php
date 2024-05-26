<?php
/**
 * Tests for PLSR_URL_Pattern_Prefixer class.
 *
 * @package speculation-rules
 */

class Test_PLSR_URL_Pattern_Prefixer extends WP_UnitTestCase {

	/**
	 * @dataProvider data_prefix_path_pattern
	 */
	public function test_prefix_path_pattern( string $base_path, string $path_pattern, string $expected ): void {
		$p = new PLSR_URL_Pattern_Prefixer( array( 'demo' => $base_path ) );

		$this->assertSame(
			$expected,
			$p->prefix_path_pattern( $path_pattern, 'demo' )
		);
	}

	/** @return array<int, mixed> */
	public function data_prefix_path_pattern(): array {
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

	public function test_get_default_contexts(): void {
		$contexts = PLSR_URL_Pattern_Prefixer::get_default_contexts();

		$this->assertArrayHasKey( 'home', $contexts );
		$this->assertArrayHasKey( 'site', $contexts );
		$this->assertSame( '/', $contexts['home'] );
		$this->assertSame( '/', $contexts['site'] );
	}

	/**
	 * @dataProvider data_default_contexts_with_subdirectories
	 */
	public function test_get_default_contexts_with_subdirectories( string $context, string $unescaped, string $expected ): void {
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

	/** @return array<int, mixed> */
	public function data_default_contexts_with_subdirectories(): array {
		return array(
			array( 'home', 'https://example.com/subdir/', '/subdir/' ),
			array( 'site', 'https://example.com/subdir/wp/', '/subdir/wp/' ),
			// If the context URL has URL pattern special characters it may need escaping.
			array( 'home', 'https://example.com/scope:0.*/', '/scope\\:0.\\*/' ),
			array( 'site', 'https://example.com/scope:0.*/wp+/', '/scope\\:0.\\*/wp\\+/' ),
		);
	}
}
