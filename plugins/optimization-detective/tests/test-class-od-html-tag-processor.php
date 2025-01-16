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
				'document'          => '
					<!DOCTYPE html>
					<html>
						<head>
							<meta charset="utf8">
							<title>Foo</title>
							<script>/*...*/</script>
							<style>/*...*/</style>
						</head>
						<body>
							<div id="page">
								<iframe src="https://example.com/"></iframe>
								<p>
									Foo!
									<br>
									<img src="https://example.com/foo.jpg" width="1000" height="600" alt="Foo">
								</p>
								<form><textarea>Write here!</textarea></form>
								<footer>The end!</footer>
							</div>
						</body>
					</html>
				',
				'open_tags'         => array( 'HTML', 'HEAD', 'META', 'TITLE', 'SCRIPT', 'STYLE', 'BODY', 'DIV', 'IFRAME', 'P', 'BR', 'IMG', 'FORM', 'TEXTAREA', 'FOOTER' ),
				'xpath_breadcrumbs' => array(
					'/HTML'                             => array( 'HTML' ),
					'/HTML/HEAD'                        => array( 'HTML', 'HEAD' ),
					'/HTML/HEAD/*[1][self::META]'       => array( 'HTML', 'HEAD', 'META' ),
					'/HTML/HEAD/*[2][self::TITLE]'      => array( 'HTML', 'HEAD', 'TITLE' ),
					'/HTML/HEAD/*[3][self::SCRIPT]'     => array( 'HTML', 'HEAD', 'SCRIPT' ),
					'/HTML/HEAD/*[4][self::STYLE]'      => array( 'HTML', 'HEAD', 'STYLE' ),
					'/HTML/BODY'                        => array( 'HTML', 'BODY' ),
					'/HTML/BODY/DIV'                    => array( 'HTML', 'BODY', 'DIV' ),
					'/HTML/BODY/DIV/*[1][self::IFRAME]' => array( 'HTML', 'BODY', 'DIV', 'IFRAME' ),
					'/HTML/BODY/DIV/*[2][self::P]'      => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[2][self::P]/*[1][self::BR]' => array( 'HTML', 'BODY', 'DIV', 'P', 'BR' ),
					'/HTML/BODY/DIV/*[2][self::P]/*[2][self::IMG]' => array( 'HTML', 'BODY', 'DIV', 'P', 'IMG' ),
					'/HTML/BODY/DIV/*[3][self::FORM]'   => array( 'HTML', 'BODY', 'DIV', 'FORM' ),
					'/HTML/BODY/DIV/*[3][self::FORM]/*[1][self::TEXTAREA]' => array( 'HTML', 'BODY', 'DIV', 'FORM', 'TEXTAREA' ),
					'/HTML/BODY/DIV/*[4][self::FOOTER]' => array( 'HTML', 'BODY', 'DIV', 'FOOTER' ),
				),
			),
			'foreign-elements'   => array(
				'document'          => '
					<html>
						<head></head>
						<body>
							<div id="page">
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
							</div>
						</body>
					</html>
				',
				'open_tags'         => array( 'HTML', 'HEAD', 'BODY', 'DIV', 'SVG', 'G', 'PATH', 'CIRCLE', 'G', 'RECT', 'MATH', 'MN', 'MSPACE', 'MN' ),
				'xpath_breadcrumbs' => array(
					'/HTML'                           => array( 'HTML' ),
					'/HTML/HEAD'                      => array( 'HTML', 'HEAD' ),
					'/HTML/BODY'                      => array( 'HTML', 'BODY' ),
					'/HTML/BODY/DIV'                  => array( 'HTML', 'BODY', 'DIV' ),
					'/HTML/BODY/DIV/*[1][self::SVG]'  => array( 'HTML', 'BODY', 'DIV', 'SVG' ),
					'/HTML/BODY/DIV/*[1][self::SVG]/*[1][self::G]' => array( 'HTML', 'BODY', 'DIV', 'SVG', 'G' ),
					'/HTML/BODY/DIV/*[1][self::SVG]/*[1][self::G]/*[1][self::PATH]' => array( 'HTML', 'BODY', 'DIV', 'SVG', 'G', 'PATH' ),
					'/HTML/BODY/DIV/*[1][self::SVG]/*[1][self::G]/*[2][self::CIRCLE]' => array( 'HTML', 'BODY', 'DIV', 'SVG', 'G', 'CIRCLE' ),
					'/HTML/BODY/DIV/*[1][self::SVG]/*[1][self::G]/*[3][self::G]' => array( 'HTML', 'BODY', 'DIV', 'SVG', 'G', 'G' ),
					'/HTML/BODY/DIV/*[1][self::SVG]/*[1][self::G]/*[4][self::RECT]' => array( 'HTML', 'BODY', 'DIV', 'SVG', 'G', 'RECT' ),
					'/HTML/BODY/DIV/*[2][self::MATH]' => array( 'HTML', 'BODY', 'DIV', 'MATH' ),
					'/HTML/BODY/DIV/*[2][self::MATH]/*[1][self::MN]' => array( 'HTML', 'BODY', 'DIV', 'MATH', 'MN' ),
					'/HTML/BODY/DIV/*[2][self::MATH]/*[2][self::MSPACE]' => array( 'HTML', 'BODY', 'DIV', 'MATH', 'MSPACE' ),
					'/HTML/BODY/DIV/*[2][self::MATH]/*[3][self::MN]' => array( 'HTML', 'BODY', 'DIV', 'MATH', 'MN' ),
				),
			),
			'closing-void-tag'   => array(
				'document'          => '
					<html>
						<head></head>
						<body>
							<div id="page">
								<span>1</span>
								<meta></meta>
								<span>2</span>
							</div>
						</body>
					</html>
				',
				'open_tags'         => array( 'HTML', 'HEAD', 'BODY', 'DIV', 'SPAN', 'META', 'SPAN' ),
				'xpath_breadcrumbs' => array(
					'/HTML'                           => array( 'HTML' ),
					'/HTML/HEAD'                      => array( 'HTML', 'HEAD' ),
					'/HTML/BODY'                      => array( 'HTML', 'BODY' ),
					'/HTML/BODY/DIV'                  => array( 'HTML', 'BODY', 'DIV' ),
					'/HTML/BODY/DIV/*[1][self::SPAN]' => array( 'HTML', 'BODY', 'DIV', 'SPAN' ),
					'/HTML/BODY/DIV/*[2][self::META]' => array( 'HTML', 'BODY', 'DIV', 'META' ),
					'/HTML/BODY/DIV/*[3][self::SPAN]' => array( 'HTML', 'BODY', 'DIV', 'SPAN' ),
				),
			),
			'void-tags'          => array(
				'document'          => '
					<html>
						<head></head>
						<body>
							<div id="page">
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
							</div>
						</body>
					</html>
				',
				'open_tags'         => array( 'HTML', 'HEAD', 'BODY', 'DIV', 'AREA', 'BASE', 'BASEFONT', 'BGSOUND', 'BR', 'COL', 'EMBED', 'FRAME', 'HR', 'IMG', 'INPUT', 'KEYGEN', 'LINK', 'META', 'PARAM', 'SOURCE', 'TRACK', 'WBR', 'DIV', 'SPAN', 'EM' ),
				'xpath_breadcrumbs' => array(
					'/HTML'                               => array( 'HTML' ),
					'/HTML/HEAD'                          => array( 'HTML', 'HEAD' ),
					'/HTML/BODY'                          => array( 'HTML', 'BODY' ),
					'/HTML/BODY/DIV'                      => array( 'HTML', 'BODY', 'DIV' ),
					'/HTML/BODY/DIV/*[1][self::AREA]'     => array( 'HTML', 'BODY', 'DIV', 'AREA' ),
					'/HTML/BODY/DIV/*[2][self::BASE]'     => array( 'HTML', 'BODY', 'DIV', 'BASE' ),
					'/HTML/BODY/DIV/*[3][self::BASEFONT]' => array( 'HTML', 'BODY', 'DIV', 'BASEFONT' ),
					'/HTML/BODY/DIV/*[4][self::BGSOUND]'  => array( 'HTML', 'BODY', 'DIV', 'BGSOUND' ),
					'/HTML/BODY/DIV/*[5][self::BR]'       => array( 'HTML', 'BODY', 'DIV', 'BR' ),
					'/HTML/BODY/DIV/*[6][self::COL]'      => array( 'HTML', 'BODY', 'DIV', 'COL' ),
					'/HTML/BODY/DIV/*[7][self::EMBED]'    => array( 'HTML', 'BODY', 'DIV', 'EMBED' ),
					'/HTML/BODY/DIV/*[8][self::FRAME]'    => array( 'HTML', 'BODY', 'DIV', 'FRAME' ),
					'/HTML/BODY/DIV/*[9][self::HR]'       => array( 'HTML', 'BODY', 'DIV', 'HR' ),
					'/HTML/BODY/DIV/*[10][self::IMG]'     => array( 'HTML', 'BODY', 'DIV', 'IMG' ),
					'/HTML/BODY/DIV/*[11][self::INPUT]'   => array( 'HTML', 'BODY', 'DIV', 'INPUT' ),
					'/HTML/BODY/DIV/*[12][self::KEYGEN]'  => array( 'HTML', 'BODY', 'DIV', 'KEYGEN' ),
					'/HTML/BODY/DIV/*[13][self::LINK]'    => array( 'HTML', 'BODY', 'DIV', 'LINK' ),
					'/HTML/BODY/DIV/*[14][self::META]'    => array( 'HTML', 'BODY', 'DIV', 'META' ),
					'/HTML/BODY/DIV/*[15][self::PARAM]'   => array( 'HTML', 'BODY', 'DIV', 'PARAM' ),
					'/HTML/BODY/DIV/*[16][self::SOURCE]'  => array( 'HTML', 'BODY', 'DIV', 'SOURCE' ),
					'/HTML/BODY/DIV/*[17][self::TRACK]'   => array( 'HTML', 'BODY', 'DIV', 'TRACK' ),
					'/HTML/BODY/DIV/*[18][self::WBR]'     => array( 'HTML', 'BODY', 'DIV', 'WBR' ),
					'/HTML/BODY/DIV/*[19][self::DIV]'     => array( 'HTML', 'BODY', 'DIV', 'DIV' ),
					'/HTML/BODY/DIV/*[19][self::DIV]/*[1][self::SPAN]' => array( 'HTML', 'BODY', 'DIV', 'DIV', 'SPAN' ),
					'/HTML/BODY/DIV/*[19][self::DIV]/*[1][self::SPAN]/*[1][self::EM]' => array( 'HTML', 'BODY', 'DIV', 'DIV', 'SPAN', 'EM' ),
				),
			),
			'optional-closing-p' => array(
				'document'          => '
					<html>
						<head></head>
						<body>
							<div id="page">
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
							</div>
						</body>
					</html>
				',
				'open_tags'         => array( 'HTML', 'HEAD', 'BODY', 'DIV', 'P', 'P', 'EM', 'P', 'P', 'ADDRESS', 'P', 'ARTICLE', 'P', 'ASIDE', 'P', 'BLOCKQUOTE', 'P', 'DETAILS', 'P', 'DIV', 'P', 'DL', 'P', 'FIELDSET', 'P', 'FIGCAPTION', 'P', 'FIGURE', 'P', 'FOOTER', 'P', 'FORM', 'P', 'H1', 'P', 'H2', 'P', 'H3', 'P', 'H4', 'P', 'H5', 'P', 'H6', 'P', 'HEADER', 'P', 'HGROUP', 'P', 'HR', 'P', 'MAIN', 'P', 'MENU', 'P', 'NAV', 'P', 'OL', 'P', 'PRE', 'P', 'SEARCH', 'P', 'SECTION', 'P', 'TABLE', 'P', 'UL' ),
				'xpath_breadcrumbs' => array(
					'/HTML'                                => array( 'HTML' ),
					'/HTML/HEAD'                           => array( 'HTML', 'HEAD' ),
					'/HTML/BODY'                           => array( 'HTML', 'BODY' ),
					'/HTML/BODY/DIV'                       => array( 'HTML', 'BODY', 'DIV' ),
					'/HTML/BODY/DIV/*[1][self::P]'         => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[2][self::P]'         => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[2][self::P]/*[1][self::EM]' => array( 'HTML', 'BODY', 'DIV', 'P', 'EM' ),
					'/HTML/BODY/DIV/*[3][self::P]'         => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[4][self::P]'         => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[5][self::ADDRESS]'   => array( 'HTML', 'BODY', 'DIV', 'ADDRESS' ),
					'/HTML/BODY/DIV/*[6][self::P]'         => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[7][self::ARTICLE]'   => array( 'HTML', 'BODY', 'DIV', 'ARTICLE' ),
					'/HTML/BODY/DIV/*[8][self::P]'         => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[9][self::ASIDE]'     => array( 'HTML', 'BODY', 'DIV', 'ASIDE' ),
					'/HTML/BODY/DIV/*[10][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[11][self::BLOCKQUOTE]' => array( 'HTML', 'BODY', 'DIV', 'BLOCKQUOTE' ),
					'/HTML/BODY/DIV/*[12][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[13][self::DETAILS]'  => array( 'HTML', 'BODY', 'DIV', 'DETAILS' ),
					'/HTML/BODY/DIV/*[14][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[15][self::DIV]'      => array( 'HTML', 'BODY', 'DIV', 'DIV' ),
					'/HTML/BODY/DIV/*[16][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[17][self::DL]'       => array( 'HTML', 'BODY', 'DIV', 'DL' ),
					'/HTML/BODY/DIV/*[18][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[19][self::FIELDSET]' => array( 'HTML', 'BODY', 'DIV', 'FIELDSET' ),
					'/HTML/BODY/DIV/*[20][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[21][self::FIGCAPTION]' => array( 'HTML', 'BODY', 'DIV', 'FIGCAPTION' ),
					'/HTML/BODY/DIV/*[22][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[23][self::FIGURE]'   => array( 'HTML', 'BODY', 'DIV', 'FIGURE' ),
					'/HTML/BODY/DIV/*[24][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[25][self::FOOTER]'   => array( 'HTML', 'BODY', 'DIV', 'FOOTER' ),
					'/HTML/BODY/DIV/*[26][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[27][self::FORM]'     => array( 'HTML', 'BODY', 'DIV', 'FORM' ),
					'/HTML/BODY/DIV/*[28][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[29][self::H1]'       => array( 'HTML', 'BODY', 'DIV', 'H1' ),
					'/HTML/BODY/DIV/*[30][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[31][self::H2]'       => array( 'HTML', 'BODY', 'DIV', 'H2' ),
					'/HTML/BODY/DIV/*[32][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[33][self::H3]'       => array( 'HTML', 'BODY', 'DIV', 'H3' ),
					'/HTML/BODY/DIV/*[34][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[35][self::H4]'       => array( 'HTML', 'BODY', 'DIV', 'H4' ),
					'/HTML/BODY/DIV/*[36][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[37][self::H5]'       => array( 'HTML', 'BODY', 'DIV', 'H5' ),
					'/HTML/BODY/DIV/*[38][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[39][self::H6]'       => array( 'HTML', 'BODY', 'DIV', 'H6' ),
					'/HTML/BODY/DIV/*[40][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[41][self::HEADER]'   => array( 'HTML', 'BODY', 'DIV', 'HEADER' ),
					'/HTML/BODY/DIV/*[42][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[43][self::HGROUP]'   => array( 'HTML', 'BODY', 'DIV', 'HGROUP' ),
					'/HTML/BODY/DIV/*[44][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[45][self::HR]'       => array( 'HTML', 'BODY', 'DIV', 'HR' ),
					'/HTML/BODY/DIV/*[46][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[47][self::MAIN]'     => array( 'HTML', 'BODY', 'DIV', 'MAIN' ),
					'/HTML/BODY/DIV/*[48][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[49][self::MENU]'     => array( 'HTML', 'BODY', 'DIV', 'MENU' ),
					'/HTML/BODY/DIV/*[50][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[51][self::NAV]'      => array( 'HTML', 'BODY', 'DIV', 'NAV' ),
					'/HTML/BODY/DIV/*[52][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[53][self::OL]'       => array( 'HTML', 'BODY', 'DIV', 'OL' ),
					'/HTML/BODY/DIV/*[54][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[55][self::PRE]'      => array( 'HTML', 'BODY', 'DIV', 'PRE' ),
					'/HTML/BODY/DIV/*[56][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[57][self::SEARCH]'   => array( 'HTML', 'BODY', 'DIV', 'SEARCH' ),
					'/HTML/BODY/DIV/*[58][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[59][self::SECTION]'  => array( 'HTML', 'BODY', 'DIV', 'SECTION' ),
					'/HTML/BODY/DIV/*[60][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[61][self::TABLE]'    => array( 'HTML', 'BODY', 'DIV', 'TABLE' ),
					'/HTML/BODY/DIV/*[62][self::P]'        => array( 'HTML', 'BODY', 'DIV', 'P' ),
					'/HTML/BODY/DIV/*[63][self::UL]'       => array( 'HTML', 'BODY', 'DIV', 'UL' ),
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
	 * @covers ::get_breadcrumbs
	 *
	 * @dataProvider data_provider_sample_documents
	 *
	 * @param string                $document          Document.
	 * @param string[]              $open_tags         Open tags.
	 * @param array<string, string> $xpath_breadcrumbs XPaths mapped to their breadcrumbs.
	 */
	public function test_next_tag_and_get_xpath( string $document, array $open_tags, array $xpath_breadcrumbs ): void {
		$p = new OD_HTML_Tag_Processor( $document );
		$this->assertSame( '', $p->get_xpath(), 'Expected empty XPath since iteration has not started.' );
		$actual_open_tags                 = array();
		$actual_xpath_breadcrumbs_mapping = array();
		while ( $p->next_open_tag() ) {
			$actual_open_tags[] = $p->get_tag();

			$xpath = $p->get_xpath();
			$this->assertArrayNotHasKey( $xpath, $actual_xpath_breadcrumbs_mapping, 'Each tag must have a unique XPath.' );

			$actual_xpath_breadcrumbs_mapping[ $xpath ] = $p->get_breadcrumbs();
		}

		$this->assertSame( $open_tags, $actual_open_tags, "Expected list of open tags to match.\nSnapshot: " . $this->export_array_snapshot( $actual_open_tags, true ) );
		$this->assertSame( $xpath_breadcrumbs, $actual_xpath_breadcrumbs_mapping, "Expected list of XPaths to match.\nSnapshot: " . $this->export_array_snapshot( $actual_xpath_breadcrumbs_mapping ) );
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
		$processor = new OD_HTML_Tag_Processor( '<html lang="en" class="foo" dir="ltr" data-novalue></html>' );
		while ( $processor->next_open_tag() ) {
			$open_tag = $processor->get_tag();
			if ( 'HTML' === $open_tag ) {
				$processor->set_attribute( 'lang', 'es' );
				$processor->set_attribute( 'class', 'foo' ); // Unchanged from source to test that data-od-replaced-class metadata attribute won't be added.
				$processor->remove_attribute( 'dir' );
				$processor->set_attribute( 'id', 'root' );
				$processor->set_meta_attribute( 'foo', 'bar' );
				$processor->set_meta_attribute( 'baz', true );
				$processor->set_attribute( 'data-novalue', 'Nevermind!' );
			}
		}
		$this->assertSame(
			'<html data-od-added-id data-od-baz data-od-foo="bar" data-od-removed-dir="ltr" data-od-replaced-data-novalue data-od-replaced-lang="en" id="root" lang="es" class="foo"  data-novalue="Nevermind!"></html>',
			$processor->get_updated_html()
		);
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
						<div id="page">
							<iframe src="https://example.net/"></iframe>
							<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio">
								<div class="wp-block-embed__wrapper">
									<iframe title="Matt Mullenweg: State of the Word 2023" width="750" height="422" src="https://www.youtube.com/embed/c7M4mBVgP3Y?feature=oembed" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
								</div>
								<figcaption>This is the State of the Word!</figcaption>
							</figure>
							<iframe src="https://example.com/"></iframe>
							<img src="https://example.com/foo.jpg">
						</div>
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
				'xpath' => '/HTML/BODY/DIV/*[2][self::FIGURE]',
				'depth' => 4,
			),
			array(
				'tag'   => 'DIV',
				'xpath' => '/HTML/BODY/DIV/*[2][self::FIGURE]/*[1][self::DIV]',
				'depth' => 5,
			),
			array(
				'tag'   => 'IFRAME',
				'xpath' => '/HTML/BODY/DIV/*[2][self::FIGURE]/*[1][self::DIV]/*[1][self::IFRAME]',
				'depth' => 6,
			),
			array(
				'tag'   => 'FIGCAPTION',
				'xpath' => '/HTML/BODY/DIV/*[2][self::FIGURE]/*[2][self::FIGCAPTION]',
				'depth' => 5,
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
		$php  = 'array(';
		$php .= $one_line ? ' ' : "\n";
		foreach ( $data as $key => $value ) {
			if ( ! $one_line ) {
				$php .= "\t";
			}
			if ( ! is_numeric( $key ) ) {
				$php .= var_export( $key, true ) . ' => ';
			}

			if ( is_array( $value ) ) {
				$php .= $this->export_array_snapshot( $value, true );
			} else {
				$php .= str_replace( "\n", ' ', var_export( $value, true ) );
			}
			$php .= ',';
			$php .= $one_line ? ' ' : "\n";
		}
		$php .= ')';
		return $php;
	}
}
