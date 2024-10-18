<?php
/**
 * Tests for optimization-detective class OD_HTML_Tag_Processor.
 *
 * @package optimization-detective
 *
 * @coversDefaultClass OD_HTML_Tag_Processor
 *
 * @noinspection HtmlRequiredTitleElement
 * @noinspection HtmlRequiredAltAttribute
 * @noinspection HtmlRequiredLangAttribute
 * @noinspection HtmlDeprecatedTag
 * @noinspection HtmlDeprecatedAttribute
 * @noinspection HtmlExtraClosingTag
 * @todo What are the other inspection IDs which can turn off inspections for the other irrelevant warnings? Remaining is "The tag is marked as deprecated."
 */
class Test_OD_HTML_Tag_Processor extends WP_UnitTestCase {

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_sample_documents(): array {
		return array(
			'well-formed-html'   => array(
				'document'  => '
					<!DOCTYPE html>
					<html>
						<head>
							<meta charset="utf8">
							<title>Foo</title>
							<script>/*...*/</script>
							<style>/*...*/</style>
						</head>
						<body>
							<iframe src="https://example.com/"></iframe>
							<p>
								Foo!
								<br>
								<img src="https://example.com/foo.jpg" width="1000" height="600" alt="Foo">
							</p>
							<form><textarea>Write here!</textarea></form>
							<footer>The end!</footer>
						</body>
					</html>
				',
				'open_tags' => array( 'HTML', 'HEAD', 'META', 'TITLE', 'SCRIPT', 'STYLE', 'BODY', 'IFRAME', 'P', 'BR', 'IMG', 'FORM', 'TEXTAREA', 'FOOTER' ),
				'xpaths'    => array(
					'/*[1][self::HTML]',
					'/*[1][self::HTML]/*[1][self::HEAD]',
					'/*[1][self::HTML]/*[1][self::HEAD]/*[1][self::META]',
					'/*[1][self::HTML]/*[1][self::HEAD]/*[2][self::TITLE]',
					'/*[1][self::HTML]/*[1][self::HEAD]/*[3][self::SCRIPT]',
					'/*[1][self::HTML]/*[1][self::HEAD]/*[4][self::STYLE]',
					'/*[1][self::HTML]/*[2][self::BODY]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IFRAME]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]/*[1][self::BR]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]/*[2][self::IMG]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[3][self::FORM]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[3][self::FORM]/*[1][self::TEXTAREA]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[4][self::FOOTER]',
				),
			),
			'foreign-elements'   => array(
				'document'  => '
					<html>
						<head></head>
						<body>
							<svg>
								<g>
									<path d="M10 10"/>
									<circle cx="10" cy="10" r="2" fill="red"/>
									<g />
									<rect width="100%" height="100%" fill="red" />
								</g>
							</svg>
							<math display="block">
								<mn>1</mn>
								<mspace depth="40px" height="20px" width="100px" style="background: lightblue;"/>
								<mn>2</mn>
							</math>
						</body>
					</html>
				',
				'open_tags' => array( 'HTML', 'HEAD', 'BODY', 'SVG', 'G', 'PATH', 'CIRCLE', 'G', 'RECT', 'MATH', 'MN', 'MSPACE', 'MN' ),
				'xpaths'    => array(
					'/*[1][self::HTML]',
					'/*[1][self::HTML]/*[1][self::HEAD]',
					'/*[1][self::HTML]/*[2][self::BODY]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::SVG]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::SVG]/*[1][self::G]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::SVG]/*[1][self::G]/*[1][self::PATH]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::SVG]/*[1][self::G]/*[2][self::CIRCLE]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::SVG]/*[1][self::G]/*[3][self::G]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::SVG]/*[1][self::G]/*[4][self::RECT]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::MATH]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::MATH]/*[1][self::MN]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::MATH]/*[2][self::MSPACE]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::MATH]/*[3][self::MN]',
				),
			),
			'closing-void-tag'   => array(
				'document'  => '
					<html>
						<head></head>
						<body>
							<span>1</span>
							<meta></meta>
							<span>2</span>
						</body>
					</html>
				',
				'open_tags' => array( 'HTML', 'HEAD', 'BODY', 'SPAN', 'META', 'SPAN' ),
				'xpaths'    => array(
					'/*[1][self::HTML]',
					'/*[1][self::HTML]/*[1][self::HEAD]',
					'/*[1][self::HTML]/*[2][self::BODY]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::SPAN]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::META]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[3][self::SPAN]',
				),
			),
			'void-tags'          => array(
				'document'  => '
					<html>
						<head></head>
						<body>
							<area>
							<base>
							<basefont>
							<bgsound>
							<br>
							<col>
							<embed>
							<frame>
							<hr>
							<img src="">
							<input>
							<keygen>
							<link>
							<meta>
							<param name="foo" value="bar">
							<source>
							<track src="https://example.com/track">
							<wbr>

							<!-- The following are not void -->
							<div>
							<span>
							<em>
						</body>
					</html>
				',
				'open_tags' => array( 'HTML', 'HEAD', 'BODY', 'AREA', 'BASE', 'BASEFONT', 'BGSOUND', 'BR', 'COL', 'EMBED', 'FRAME', 'HR', 'IMG', 'INPUT', 'KEYGEN', 'LINK', 'META', 'PARAM', 'SOURCE', 'TRACK', 'WBR', 'DIV', 'SPAN', 'EM' ),
				'xpaths'    => array(
					'/*[1][self::HTML]',
					'/*[1][self::HTML]/*[1][self::HEAD]',
					'/*[1][self::HTML]/*[2][self::BODY]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::AREA]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::BASE]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[3][self::BASEFONT]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[4][self::BGSOUND]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[5][self::BR]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[6][self::COL]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[7][self::EMBED]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[8][self::FRAME]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[9][self::HR]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[10][self::IMG]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[11][self::INPUT]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[12][self::KEYGEN]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[13][self::LINK]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[14][self::META]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[15][self::PARAM]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[16][self::SOURCE]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[17][self::TRACK]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[18][self::WBR]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[19][self::DIV]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[19][self::DIV]/*[1][self::SPAN]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[19][self::DIV]/*[1][self::SPAN]/*[1][self::EM]',
				),
			),
			'optional-closing-p' => array(
				'document'  => '
					<html>
						<head></head>
						<body>
							<!-- In HTML, the closing paragraph tag is optional. -->
							<p>First
							<p><em>Second</em>
							<p>Third

							<!-- Try triggering all closing -->
							<p><address></address>
							<p><article></article>
							<p><aside></aside>
							<p><blockquote></blockquote>
							<p><details></details>
							<p><div></div>
							<p><dl></dl>
							<p><fieldset></fieldset>
							<p><figcaption></figcaption>
							<p><figure></figure>
							<p><footer></footer>
							<p><form></form>
							<p><h1></h1>
							<p><h2></h2>
							<p><h3></h3>
							<p><h4></h4>
							<p><h5></h5>
							<p><h6></h6>
							<p><header></header>
							<p><hgroup></hgroup>
							<p><hr>
							<p><main></main>
							<p><menu></menu>
							<p><nav></nav>
							<p><ol></ol>
							<p><pre></pre>
							<p><search></search>
							<p><section></section>
							<p><table></table>
							<p><ul></ul>
						</body>
					</html>
				',
				'open_tags' => array( 'HTML', 'HEAD', 'BODY', 'P', 'P', 'EM', 'P', 'P', 'ADDRESS', 'P', 'ARTICLE', 'P', 'ASIDE', 'P', 'BLOCKQUOTE', 'P', 'DETAILS', 'P', 'DIV', 'P', 'DL', 'P', 'FIELDSET', 'P', 'FIGCAPTION', 'P', 'FIGURE', 'P', 'FOOTER', 'P', 'FORM', 'P', 'H1', 'P', 'H2', 'P', 'H3', 'P', 'H4', 'P', 'H5', 'P', 'H6', 'P', 'HEADER', 'P', 'HGROUP', 'P', 'HR', 'P', 'MAIN', 'P', 'MENU', 'P', 'NAV', 'P', 'OL', 'P', 'PRE', 'P', 'SEARCH', 'P', 'SECTION', 'P', 'TABLE', 'P', 'UL' ),
				'xpaths'    => array(
					'/*[1][self::HTML]',
					'/*[1][self::HTML]/*[1][self::HEAD]',
					'/*[1][self::HTML]/*[2][self::BODY]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::P]/*[1][self::EM]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::ADDRESS]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::ARTICLE]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::ASIDE]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::BLOCKQUOTE]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::DETAILS]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::DIV]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::DL]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIELDSET]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGCAPTION]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FOOTER]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FORM]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::H1]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::H2]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::H3]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::H4]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::H5]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::H6]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::HEADER]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::HGROUP]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::HR]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::MAIN]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::MENU]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::NAV]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::OL]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::PRE]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::SEARCH]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::SECTION]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::TABLE]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]',
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::UL]',
				),
			),
		);
	}

	/**
	 * Test next_tag(), next_token(), and get_xpath().
	 *
	 * @covers ::next_open_tag
	 * @covers ::next_tag
	 * @covers ::next_token
	 * @covers ::get_xpath
	 *
	 * @dataProvider data_provider_sample_documents
	 *
	 * @param string   $document Document.
	 * @param string[] $open_tags Open tags.
	 * @param string[] $xpaths XPaths.
	 */
	public function test_next_tag_and_get_xpath( string $document, array $open_tags, array $xpaths ): void {
		$p = new OD_HTML_Tag_Processor( $document );
		$this->assertSame( '', $p->get_xpath(), 'Expected empty XPath since iteration has not started.' );
		$actual_open_tags = array();
		$actual_xpaths    = array();
		while ( $p->next_open_tag() ) {
			$actual_open_tags[] = $p->get_tag();
			$actual_xpaths[]    = $p->get_xpath();
		}

		$this->assertSame( $open_tags, $actual_open_tags, "Expected list of open tags to match.\nSnapshot: " . $this->export_array_snapshot( $actual_open_tags, true ) );
		$this->assertSame( $xpaths, $actual_xpaths, "Expected list of XPaths to match.\nSnapshot: " . $this->export_array_snapshot( $actual_xpaths ) );
	}

	/**
	 * Test next_tag() passing query which is invalid.
	 *
	 * @covers ::next_tag
	 */
	public function test_next_tag_with_query(): void {
		$this->expectException( InvalidArgumentException::class );
		$p = new OD_HTML_Tag_Processor( '<html></html>' );
		$p->next_tag( array( 'tag_name' => 'HTML' ) );
	}

	/**
	 * Test both append_head_html() and append_body_html().
	 *
	 * @covers ::append_head_html
	 * @covers ::append_body_html
	 * @covers ::get_updated_html
	 */
	public function test_append_head_and_body_html(): void {
		$html                = '
			<html>
				<head>
					<meta charset=utf-8>
					<!-- </head> -->
				</head>
				<!--</HEAD>-->
				<body>
					<h1>Hello World</h1>
					<!-- </body> -->
				</body>
				<!--</BODY>-->
			</html>
		';
		$head_injected       = '<link rel="home" href="/">';
		$body_injected       = '<script>document.write("Goodbye!")</script>';
		$later_head_injected = '<!-- Later injection -->';
		$processor           = new OD_HTML_Tag_Processor( $html );

		$processor->append_head_html( $head_injected );
		$processor->append_body_html( $body_injected );

		$saw_head = false;
		$saw_body = false;
		$did_seek = false;
		while ( $processor->next_open_tag() ) {
			$this->assertStringNotContainsString( $head_injected, $processor->get_updated_html(), 'Only expecting end-of-head injection once document was finalized.' );
			$this->assertStringNotContainsString( $body_injected, $processor->get_updated_html(), 'Only expecting end-of-body injection once document was finalized.' );
			$tag = $processor->get_tag();
			if ( 'HEAD' === $tag ) {
				$saw_head = true;
			} elseif ( 'BODY' === $tag ) {
				$saw_body = true;
				$this->assertTrue( $processor->set_bookmark( 'cuerpo' ) );
			}
			if ( ! $did_seek && 'H1' === $tag ) {
				$processor->append_head_html( '<!--H1 appends to HEAD-->' );
				$processor->append_body_html( '<!--H1 appends to BODY-->' );
				$this->assertTrue( $processor->seek( 'cuerpo' ) );
				$did_seek = true;
			}
		}
		$this->assertTrue( $did_seek );
		$this->assertTrue( $saw_head );
		$this->assertTrue( $saw_body );
		$this->assertStringContainsString( $head_injected, $processor->get_updated_html(), 'Only expecting end-of-head injection once document was finalized.' );
		$this->assertStringContainsString( $body_injected, $processor->get_updated_html(), 'Only expecting end-of-body injection once document was finalized.' );

		$processor->append_head_html( $later_head_injected );

		$expected = "
			<html>
				<head>
					<meta charset=utf-8>
					<!-- </head> -->
				{$head_injected}<!--H1 appends to HEAD-->{$later_head_injected}</head>
				<!--</HEAD>-->
				<body>
					<h1>Hello World</h1>
					<!-- </body> -->
				{$body_injected}<!--H1 appends to BODY--></body>
				<!--</BODY>-->
			</html>
		";
		$this->assertSame( $expected, $processor->get_updated_html() );
	}

	/**
	 * Test get_tag(), get_attribute(), set_attribute(), remove_attribute(), and get_updated_html().
	 *
	 * @covers ::set_attribute
	 * @covers ::remove_attribute
	 * @covers ::set_meta_attribute
	 */
	public function test_html_tag_processor_wrapper_methods(): void {
		$processor = new OD_HTML_Tag_Processor( '<html lang="en" class="foo" dir="ltr"></html>' );
		while ( $processor->next_open_tag() ) {
			$open_tag = $processor->get_tag();
			if ( 'HTML' === $open_tag ) {
				$processor->set_attribute( 'lang', 'es' );
				$processor->set_attribute( 'class', 'foo' ); // Unchanged.
				$processor->remove_attribute( 'dir' );
				$processor->set_attribute( 'id', 'root' );
				$processor->set_meta_attribute( 'foo', 'bar' );
				$processor->set_meta_attribute( 'baz', true );
			}
		}
		$this->assertSame( '<html data-od-added-id data-od-baz data-od-foo="bar" data-od-removed-dir="ltr" data-od-replaced-lang="en" id="root" lang="es" class="foo" ></html>', $processor->get_updated_html() );
	}

	/**
	 * Test bookmarking and seeking.
	 *
	 * @covers ::set_bookmark
	 * @covers ::seek
	 * @covers ::release_bookmark
	 */
	public function test_bookmarking_and_seeking(): void {
		$processor = new OD_HTML_Tag_Processor(
			trim(
				'
				<html>
					<head></head>
					<body>
						<iframe src="https://example.net/"></iframe>
						<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio">
							<div class="wp-block-embed__wrapper">
								<iframe title="Matt Mullenweg: State of the Word 2023" width="750" height="422" src="https://www.youtube.com/embed/c7M4mBVgP3Y?feature=oembed" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
							</div>
							<figcaption>This is the State of the Word!</figcaption>
						</figure>
						<iframe src="https://example.com/"></iframe>
						<img src="https://example.com/foo.jpg">
					</body>
				</html>
				'
			)
		);

		$actual_figure_contents = array();
		$last_cursor_move_count = $processor->get_cursor_move_count();
		$this->assertSame( 0, $last_cursor_move_count );

		$bookmarks = array();
		while ( $processor->next_open_tag() ) {
			$this_cursor_move_count = $processor->get_cursor_move_count();
			$this->assertGreaterThan( $last_cursor_move_count, $this_cursor_move_count );
			$last_cursor_move_count = $this_cursor_move_count;
			if (
				'FIGURE' === $processor->get_tag()
				&&
				true === $processor->has_class( 'wp-block-embed' )
			) {
				$embed_block_depth = $processor->get_current_depth();
				do {
					if ( ! $processor->is_tag_closer() ) {
						$bookmark = $processor->get_tag();
						$processor->set_bookmark( $bookmark );
						$bookmarks[]              = $bookmark;
						$actual_figure_contents[] = array(
							'tag'   => $processor->get_tag(),
							'xpath' => $processor->get_xpath(),
							'depth' => $processor->get_current_depth(),
						);
					}
					if ( $processor->get_current_depth() < $embed_block_depth ) {
						break;
					}
				} while ( $processor->next_tag() );
			}
		}

		$expected_figure_contents = array(
			array(
				'tag'   => 'FIGURE',
				'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::FIGURE]',
				'depth' => 3,
			),
			array(
				'tag'   => 'DIV',
				'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::FIGURE]/*[1][self::DIV]',
				'depth' => 4,
			),
			array(
				'tag'   => 'IFRAME',
				'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::FIGURE]/*[1][self::DIV]/*[1][self::IFRAME]',
				'depth' => 5,
			),
			array(
				'tag'   => 'FIGCAPTION',
				'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::FIGURE]/*[2][self::FIGCAPTION]',
				'depth' => 4,
			),
		);

		$this->assertSame( $expected_figure_contents, $actual_figure_contents );

		$sought_actual_contents = array();
		foreach ( $bookmarks as $bookmark ) {
			$processor->seek( $bookmark );
			$sought_actual_contents[] = array(
				'tag'   => $processor->get_tag(),
				'xpath' => $processor->get_xpath(),
				'depth' => $processor->get_current_depth(),
			);
		}

		$this->assertSame( $expected_figure_contents, $sought_actual_contents );

		$this->assertTrue( $processor->has_bookmark( 'FIGURE' ) );
		$this->assertTrue( $processor->has_bookmark( 'DIV' ) );
		$this->assertTrue( $processor->has_bookmark( 'IFRAME' ) );
		$this->assertTrue( $processor->has_bookmark( 'FIGCAPTION' ) );
		$this->assertFalse( $processor->has_bookmark( 'IMG' ) );
		$processor->seek( 'IFRAME' );
		$processor->set_attribute( 'loading', 'lazy' );

		$this->assertStringContainsString(
			'<iframe data-od-added-loading loading="lazy" title="Matt Mullenweg: State of the Word 2023" width="750" height="422" src="https://www.youtube.com/embed/c7M4mBVgP3Y?feature=oembed" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>',
			$processor->get_updated_html()
		);

		$processor->release_bookmark( 'FIGURE' );
		$this->assertFalse( $processor->has_bookmark( 'FIGURE' ) );

		// TODO: Try adding too many bookmarks.
	}

	/**
	 * Test get_cursor_move_count().
	 *
	 * @covers ::get_cursor_move_count
	 */
	public function test_get_cursor_move_count(): void {
		$processor = new OD_HTML_Tag_Processor(
			trim(
				'
				<html>
					<head></head>
					<body></body>
				</html>
				'
			)
		);
		$this->assertSame( 0, $processor->get_cursor_move_count() );
		$this->assertTrue( $processor->next_tag() );
		$this->assertSame( 'HTML', $processor->get_tag() );
		$this->assertTrue( $processor->set_bookmark( 'document_root' ) );
		$this->assertSame( 1, $processor->get_cursor_move_count() );
		$this->assertTrue( $processor->next_tag() );
		$this->assertSame( 'HEAD', $processor->get_tag() );
		$this->assertSame( 3, $processor->get_cursor_move_count() ); // Note that next_token() call #2 was for the whitespace between <html> and <head>.
		$this->assertTrue( $processor->next_tag() );
		$this->assertSame( 'HEAD', $processor->get_tag() );
		$this->assertTrue( $processor->is_tag_closer() );
		$this->assertSame( 4, $processor->get_cursor_move_count() );
		$this->assertTrue( $processor->next_tag() );
		$this->assertSame( 'BODY', $processor->get_tag() );
		$this->assertSame( 6, $processor->get_cursor_move_count() ); // Note that next_token() call #5 was for the whitespace between </head> and <body>.
		$this->assertTrue( $processor->next_tag() );
		$this->assertSame( 'BODY', $processor->get_tag() );
		$this->assertTrue( $processor->is_tag_closer() );
		$this->assertSame( 7, $processor->get_cursor_move_count() );
		$this->assertTrue( $processor->next_tag() );
		$this->assertSame( 'HTML', $processor->get_tag() );
		$this->assertTrue( $processor->is_tag_closer() );
		$this->assertSame( 9, $processor->get_cursor_move_count() ); // Note that next_token() call #8 was for the whitespace between </body> and <html>.
		$this->assertFalse( $processor->next_tag() );
		$this->assertSame( 10, $processor->get_cursor_move_count() );
		$this->assertFalse( $processor->next_tag() );
		$this->assertSame( 11, $processor->get_cursor_move_count() );
		$this->assertTrue( $processor->seek( 'document_root' ) );
		$this->assertSame( 12, $processor->get_cursor_move_count() );
		$this->setExpectedIncorrectUsage( 'WP_HTML_Tag_Processor::seek' );
		$this->assertFalse( $processor->seek( 'does_not_exist' ) );
		$this->assertSame( 12, $processor->get_cursor_move_count() ); // The bookmark does not exist so no change.
	}

	/**
	 * Export an array as a PHP literal to use as a snapshot.
	 *
	 * @param array<int|string, mixed> $data Data.
	 * @param bool                     $one_line One line.
	 * @return string Snapshot.
	 */
	private function export_array_snapshot( array $data, bool $one_line = false ): string {
		$php = (string) preg_replace( '/^\s*\d+\s*=>\s*/m', '', var_export( $data, true ) );
		if ( $one_line ) {
			$php = str_replace( "\n", ' ', $php );
		}
		return $php;
	}
}
