<?php
/**
 * Tests for Embed_Optimizer_Tag_Visitor.
 *
 * @package embed-optimizer
 *
 * @coversDefaultClass Embed_Optimizer_Tag_Visitor
 */
class Test_Embed_Optimizer_Tag_Visitor_Escape_CSS extends WP_UnitTestCase {

	/**
	 * Runs the routine before each test is executed.
	 */
	public function set_up(): void {
		parent::set_up();
		require_once dirname( __DIR__ ) . '/class-embed-optimizer-tag-visitor.php';
	}

	/**
	 * Tests escape_css().
	 *
	 * @covers ::escape_css
	 *
	 * @dataProvider data_escape_css
	 *
	 * @param string $ident    Identifier to escape.
	 * @param string $expected Expected escaped identifier.
	 */
	public function test_escape_css( string $ident, string $expected ): void {
		$visitor = new Embed_Optimizer_Tag_Visitor();
		$method  = new ReflectionMethod( $visitor, 'escape_css' );
		$method->setAccessible( true );

		$this->assertSame( $expected, $method->invoke( $visitor, $ident ) );
	}

	/**
	 * Data provider for test_escape_css.
	 *
	 * @return array<string, array{string, string}> Test cases.
	 */
	public function data_escape_css(): array {
		return array(
			'empty'             => array( '', '' ),
			'simple'            => array( 'foo', 'foo' ),
			'brackets'          => array( 'foo[bar][baz]', 'foo\[bar\]\[baz\]' ),
			'hyphen'            => array( '-', '\-' ),
			'unicode'           => array( '🌈', '🌈' ),
			'double-hyphen'     => array( '--', '--' ),
			'hyphen-digit'      => array( '-1', '-\31 ' ),
			'digit'             => array( '1', '\31 ' ),
			'digit-alpha'       => array( '1a', '\31 a' ),
			'dot'               => array( '.foo', '\.foo' ),
			'hash'              => array( '#bar', '\#bar' ),
			'space'             => array( ' ', '\ ' ),
			'null'              => array( "\0", "\xEF\xBF\xBD" ), // U+FFFD is EF BF BD in UTF-8.
			'control'           => array( "\x1F", '\1f ' ),
			'delete'            => array( "\x7F", '\7f ' ),
			'backslash'         => array( '\\', '\\\\' ),
			'underscore'        => array( '_', '_' ),
			'mixed'             => array( 'foo-bar_baz', 'foo-bar_baz' ),
			'unicode-multibyte' => array( 'é', 'é' ),
			'complex'           => array( '1.2#3-4_5', '\31 \.2\#3-4_5' ),
		);
	}
}
