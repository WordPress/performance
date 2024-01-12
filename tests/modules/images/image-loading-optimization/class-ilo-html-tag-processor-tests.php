<?php
/**
 * Tests for image-loading-optimization class ILO_HTML_Tag_Processor.
 *
 * @package performance-lab
 * @group   image-loading-optimization
 *
 * @coversDefaultClass ILO_HTML_Tag_Processor
 */
class ILO_HTML_Tag_Processor_Tests extends WP_UnitTestCase {

	public function data_provider_sample_documents(): array {
		return array(
			'well-formed-html'   => array(
				'document'  => '
					<!DOCTYPE html>
					<html>
						<head>
							<meta charset="utf8">
							<title>Foo</title>
						</head>
						<body>
							<p>
								Foo!
								<br>
								<img src="/foo.jpg" width="1000" height="600" alt="Foo">
							</p>
							<footer>The end!</footer>
						</body>
					</html>
				',
				'open_tags' => array( 'HTML', 'HEAD', 'META', 'TITLE', 'BODY', 'P', 'BR', 'IMG', 'FOOTER' ),
				'xpaths'    => array(
					'/*[0][self::HTML]',
					'/*[0][self::HTML]/*[0][self::HEAD]',
					'/*[0][self::HTML]/*[0][self::HEAD]/*[0][self::META]',
					'/*[0][self::HTML]/*[0][self::HEAD]/*[1][self::TITLE]',
					'/*[0][self::HTML]/*[1][self::BODY]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::P]/*[0][self::BR]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::P]/*[1][self::IMG]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::FOOTER]',
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
					'/*[0][self::HTML]',
					'/*[0][self::HTML]/*[0][self::HEAD]',
					'/*[0][self::HTML]/*[1][self::BODY]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::SVG]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::SVG]/*[0][self::G]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::SVG]/*[0][self::G]/*[0][self::PATH]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::SVG]/*[0][self::G]/*[1][self::CIRCLE]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::SVG]/*[0][self::G]/*[2][self::G]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::SVG]/*[0][self::G]/*[3][self::RECT]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::MATH]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::MATH]/*[0][self::MN]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::MATH]/*[1][self::MSPACE]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::MATH]/*[2][self::MN]',
				),
			),
			'closing-void-tag'   => array(
				'document'  => '
					<html>
						<head></head>
						<body>
							<span>1</span>
							<br></br>
							<span>2</span>
						</body>
					</html>
				',
				'open_tags' => array( 'HTML', 'HEAD', 'BODY', 'SPAN', 'BR', 'SPAN' ),
				'xpaths'    => array(
					'/*[0][self::HTML]',
					'/*[0][self::HTML]/*[0][self::HEAD]',
					'/*[0][self::HTML]/*[1][self::BODY]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::SPAN]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::BR]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[2][self::SPAN]',
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
							<img>
							<input>
							<keygen>
							<link>
							<meta>
							<param>
							<source>
							<track>
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
					'/*[0][self::HTML]',
					'/*[0][self::HTML]/*[0][self::HEAD]',
					'/*[0][self::HTML]/*[1][self::BODY]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::AREA]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::BASE]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[2][self::BASEFONT]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[3][self::BGSOUND]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[4][self::BR]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[5][self::COL]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[6][self::EMBED]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[7][self::FRAME]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[8][self::HR]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[9][self::IMG]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[10][self::INPUT]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[11][self::KEYGEN]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[12][self::LINK]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[13][self::META]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[14][self::PARAM]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[15][self::SOURCE]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[16][self::TRACK]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[17][self::WBR]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[18][self::DIV]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[18][self::DIV]/*[0][self::SPAN]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[18][self::DIV]/*[0][self::SPAN]/*[0][self::EM]',
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
					'/*[0][self::HTML]',
					'/*[0][self::HTML]/*[0][self::HEAD]',
					'/*[0][self::HTML]/*[1][self::BODY]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::P]/*[0][self::EM]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::ADDRESS]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::ARTICLE]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::ASIDE]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::BLOCKQUOTE]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::DETAILS]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::DIV]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::DL]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::FIELDSET]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::FIGCAPTION]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::FIGURE]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::FOOTER]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::FORM]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::H1]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::H2]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::H3]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::H4]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::H5]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::H6]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::HEADER]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::HGROUP]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::HR]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::MAIN]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::MENU]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::NAV]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::OL]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::PRE]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::SEARCH]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::SECTION]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::TABLE]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[1][self::P]',
					'/*[0][self::HTML]/*[1][self::BODY]/*[0][self::UL]',
				),
			),
		);
	}

	/**
	 * Test open_tags() and get_xpath().
	 *
	 * @covers ::open_tags
	 * @covers ::get_xpath
	 *
	 * @dataProvider data_provider_sample_documents
	 */
	public function test_open_tags_and_get_xpath( string $document, array $open_tags, array $xpaths ) {
		$p = new ILO_HTML_Tag_Processor( $document );
		$this->assertSame( '', $p->get_xpath(), 'Expected empty XPath since iteration has not started.' );
		$actual_open_tags = array();
		$actual_xpaths    = array();
		foreach ( $p->open_tags() as $open_tag ) {
			$actual_open_tags[] = $open_tag;
			$actual_xpaths[]    = $p->get_xpath();
		}

		$this->assertSame( $actual_open_tags, $open_tags, "Expected list of open tags to match.\nSnapshot: " . $this->export_array_snapshot( $actual_open_tags, true ) );
		$this->assertSame( $actual_xpaths, $xpaths, "Expected list of XPaths to match.\nSnapshot:" . $this->export_array_snapshot( $actual_xpaths ) );
	}

	/**
	 * Test get_attribute(), set_attribute(), remove_attribute(), and get_updated_html().
	 *
	 * @covers ::get_attribute
	 * @covers ::set_attribute
	 * @covers ::remove_attribute
	 * @covers ::get_updated_html
	 */
	public function test_html_tag_processor_wrapper_methods() {
		$processor = new ILO_HTML_Tag_Processor( '<html lang="en" xml:lang="en"></html>' );
		foreach ( $processor->open_tags() as $open_tag ) {
			if ( 'HTML' === $open_tag ) {
				$this->assertSame( 'en', $processor->get_attribute( 'lang' ) );
				$processor->set_attribute( 'lang', 'es' );
				$processor->remove_attribute( 'xml:lang' );
			}
		}
		$this->assertSame( '<html lang="es" ></html>', $processor->get_updated_html() );
	}

	/**
	 * Export an array as a PHP literal to use as a snapshot.
	 */
	private function export_array_snapshot( array $data, bool $one_line = false ): string {
		$php = preg_replace( '/^\s*\d+\s*=>\s*/m', '', var_export( $data, true ) );
		if ( $one_line ) {
			$php = str_replace( "\n", ' ', $php );
		}
		return $php;
	}
}
