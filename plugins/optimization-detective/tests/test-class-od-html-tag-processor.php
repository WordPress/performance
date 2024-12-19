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
				'open_tags'         => array( 'HTML', 'HEAD', 'META', 'TITLE', 'SCRIPT', 'STYLE', 'BODY', 'IFRAME', 'P', 'BR', 'IMG', 'FORM', 'TEXTAREA', 'FOOTER' ),
				'xpath_breadcrumbs' => array(
					'/*[1][self::HTML]'                  => array( 'HTML' ),
					'/*[1][self::HTML]/*[1][self::HEAD]' => array( 'HTML', 'HEAD' ),
					'/*[1][self::HTML]/*[1][self::HEAD]/*[1][self::META]' => array( 'HTML', 'HEAD', 'META' ),
					'/*[1][self::HTML]/*[1][self::HEAD]/*[2][self::TITLE]' => array( 'HTML', 'HEAD', 'TITLE' ),
					'/*[1][self::HTML]/*[1][self::HEAD]/*[3][self::SCRIPT]' => array( 'HTML', 'HEAD', 'SCRIPT' ),
					'/*[1][self::HTML]/*[1][self::HEAD]/*[4][self::STYLE]' => array( 'HTML', 'HEAD', 'STYLE' ),
					'/*[1][self::HTML]/*[2][self::BODY]' => array( 'HTML', 'BODY' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IFRAME]' => array( 'HTML', 'BODY', 'IFRAME' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]/*[1][self::BR]' => array( 'HTML', 'BODY', 'P', 'BR' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]/*[2][self::IMG]' => array( 'HTML', 'BODY', 'P', 'IMG' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[3][self::FORM]' => array( 'HTML', 'BODY', 'FORM' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[3][self::FORM]/*[1][self::TEXTAREA]' => array( 'HTML', 'BODY', 'FORM', 'TEXTAREA' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[4][self::FOOTER]' => array( 'HTML', 'BODY', 'FOOTER' ),
				),
			),
			'foreign-elements'   => array(
				'document'          => '
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
				'open_tags'         => array( 'HTML', 'HEAD', 'BODY', 'SVG', 'G', 'PATH', 'CIRCLE', 'G', 'RECT', 'MATH', 'MN', 'MSPACE', 'MN' ),
				'xpath_breadcrumbs' => array(
					'/*[1][self::HTML]'                  => array( 'HTML' ),
					'/*[1][self::HTML]/*[1][self::HEAD]' => array( 'HTML', 'HEAD' ),
					'/*[1][self::HTML]/*[2][self::BODY]' => array( 'HTML', 'BODY' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::SVG]' => array( 'HTML', 'BODY', 'SVG' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::SVG]/*[1][self::G]' => array( 'HTML', 'BODY', 'SVG', 'G' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::SVG]/*[1][self::G]/*[1][self::PATH]' => array( 'HTML', 'BODY', 'SVG', 'G', 'PATH' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::SVG]/*[1][self::G]/*[2][self::CIRCLE]' => array( 'HTML', 'BODY', 'SVG', 'G', 'CIRCLE' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::SVG]/*[1][self::G]/*[3][self::G]' => array( 'HTML', 'BODY', 'SVG', 'G', 'G' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::SVG]/*[1][self::G]/*[4][self::RECT]' => array( 'HTML', 'BODY', 'SVG', 'G', 'RECT' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::MATH]' => array( 'HTML', 'BODY', 'MATH' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::MATH]/*[1][self::MN]' => array( 'HTML', 'BODY', 'MATH', 'MN' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::MATH]/*[2][self::MSPACE]' => array( 'HTML', 'BODY', 'MATH', 'MSPACE' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::MATH]/*[3][self::MN]' => array( 'HTML', 'BODY', 'MATH', 'MN' ),
				),
			),
			'closing-void-tag'   => array(
				'document'          => '
					<html>
						<head></head>
						<body>
							<span>1</span>
							<meta></meta>
							<span>2</span>
						</body>
					</html>
				',
				'open_tags'         => array( 'HTML', 'HEAD', 'BODY', 'SPAN', 'META', 'SPAN' ),
				'xpath_breadcrumbs' => array(
					'/*[1][self::HTML]'                  => array( 'HTML' ),
					'/*[1][self::HTML]/*[1][self::HEAD]' => array( 'HTML', 'HEAD' ),
					'/*[1][self::HTML]/*[2][self::BODY]' => array( 'HTML', 'BODY' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::SPAN]' => array( 'HTML', 'BODY', 'SPAN' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::META]' => array( 'HTML', 'BODY', 'META' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[3][self::SPAN]' => array( 'HTML', 'BODY', 'SPAN' ),
				),
			),
			'void-tags'          => array(
				'document'          => '
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
				'open_tags'         => array( 'HTML', 'HEAD', 'BODY', 'AREA', 'BASE', 'BASEFONT', 'BGSOUND', 'BR', 'COL', 'EMBED', 'FRAME', 'HR', 'IMG', 'INPUT', 'KEYGEN', 'LINK', 'META', 'PARAM', 'SOURCE', 'TRACK', 'WBR', 'DIV', 'SPAN', 'EM' ),
				'xpath_breadcrumbs' => array(
					'/*[1][self::HTML]'                  => array( 'HTML' ),
					'/*[1][self::HTML]/*[1][self::HEAD]' => array( 'HTML', 'HEAD' ),
					'/*[1][self::HTML]/*[2][self::BODY]' => array( 'HTML', 'BODY' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::AREA]' => array( 'HTML', 'BODY', 'AREA' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::BASE]' => array( 'HTML', 'BODY', 'BASE' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[3][self::BASEFONT]' => array( 'HTML', 'BODY', 'BASEFONT' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[4][self::BGSOUND]' => array( 'HTML', 'BODY', 'BGSOUND' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[5][self::BR]' => array( 'HTML', 'BODY', 'BR' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[6][self::COL]' => array( 'HTML', 'BODY', 'COL' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[7][self::EMBED]' => array( 'HTML', 'BODY', 'EMBED' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[8][self::FRAME]' => array( 'HTML', 'BODY', 'FRAME' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[9][self::HR]' => array( 'HTML', 'BODY', 'HR' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[10][self::IMG]' => array( 'HTML', 'BODY', 'IMG' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[11][self::INPUT]' => array( 'HTML', 'BODY', 'INPUT' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[12][self::KEYGEN]' => array( 'HTML', 'BODY', 'KEYGEN' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[13][self::LINK]' => array( 'HTML', 'BODY', 'LINK' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[14][self::META]' => array( 'HTML', 'BODY', 'META' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[15][self::PARAM]' => array( 'HTML', 'BODY', 'PARAM' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[16][self::SOURCE]' => array( 'HTML', 'BODY', 'SOURCE' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[17][self::TRACK]' => array( 'HTML', 'BODY', 'TRACK' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[18][self::WBR]' => array( 'HTML', 'BODY', 'WBR' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[19][self::DIV]' => array( 'HTML', 'BODY', 'DIV' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[19][self::DIV]/*[1][self::SPAN]' => array( 'HTML', 'BODY', 'DIV', 'SPAN' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[19][self::DIV]/*[1][self::SPAN]/*[1][self::EM]' => array( 'HTML', 'BODY', 'DIV', 'SPAN', 'EM' ),
				),
			),
			'optional-closing-p' => array(
				'document'          => '
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
				'open_tags'         => array( 'HTML', 'HEAD', 'BODY', 'P', 'P', 'EM', 'P', 'P', 'ADDRESS', 'P', 'ARTICLE', 'P', 'ASIDE', 'P', 'BLOCKQUOTE', 'P', 'DETAILS', 'P', 'DIV', 'P', 'DL', 'P', 'FIELDSET', 'P', 'FIGCAPTION', 'P', 'FIGURE', 'P', 'FOOTER', 'P', 'FORM', 'P', 'H1', 'P', 'H2', 'P', 'H3', 'P', 'H4', 'P', 'H5', 'P', 'H6', 'P', 'HEADER', 'P', 'HGROUP', 'P', 'HR', 'P', 'MAIN', 'P', 'MENU', 'P', 'NAV', 'P', 'OL', 'P', 'PRE', 'P', 'SEARCH', 'P', 'SECTION', 'P', 'TABLE', 'P', 'UL' ),
				'xpath_breadcrumbs' => array(
					'/*[1][self::HTML]'                  => array( 'HTML' ),
					'/*[1][self::HTML]/*[1][self::HEAD]' => array( 'HTML', 'HEAD' ),
					'/*[1][self::HTML]/*[2][self::BODY]' => array( 'HTML', 'BODY' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[1][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[2][self::P]/*[1][self::EM]' => array( 'HTML', 'BODY', 'P', 'EM' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[3][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[4][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[5][self::ADDRESS]' => array( 'HTML', 'BODY', 'ADDRESS' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[6][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[7][self::ARTICLE]' => array( 'HTML', 'BODY', 'ARTICLE' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[8][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[9][self::ASIDE]' => array( 'HTML', 'BODY', 'ASIDE' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[10][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[11][self::BLOCKQUOTE]' => array( 'HTML', 'BODY', 'BLOCKQUOTE' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[12][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[13][self::DETAILS]' => array( 'HTML', 'BODY', 'DETAILS' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[14][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[15][self::DIV]' => array( 'HTML', 'BODY', 'DIV' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[16][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[17][self::DL]' => array( 'HTML', 'BODY', 'DL' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[18][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[19][self::FIELDSET]' => array( 'HTML', 'BODY', 'FIELDSET' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[20][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[21][self::FIGCAPTION]' => array( 'HTML', 'BODY', 'FIGCAPTION' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[22][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[23][self::FIGURE]' => array( 'HTML', 'BODY', 'FIGURE' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[24][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[25][self::FOOTER]' => array( 'HTML', 'BODY', 'FOOTER' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[26][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[27][self::FORM]' => array( 'HTML', 'BODY', 'FORM' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[28][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[29][self::H1]' => array( 'HTML', 'BODY', 'H1' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[30][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[31][self::H2]' => array( 'HTML', 'BODY', 'H2' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[32][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[33][self::H3]' => array( 'HTML', 'BODY', 'H3' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[34][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[35][self::H4]' => array( 'HTML', 'BODY', 'H4' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[36][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[37][self::H5]' => array( 'HTML', 'BODY', 'H5' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[38][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[39][self::H6]' => array( 'HTML', 'BODY', 'H6' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[40][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[41][self::HEADER]' => array( 'HTML', 'BODY', 'HEADER' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[42][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[43][self::HGROUP]' => array( 'HTML', 'BODY', 'HGROUP' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[44][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[45][self::HR]' => array( 'HTML', 'BODY', 'HR' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[46][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[47][self::MAIN]' => array( 'HTML', 'BODY', 'MAIN' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[48][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[49][self::MENU]' => array( 'HTML', 'BODY', 'MENU' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[50][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[51][self::NAV]' => array( 'HTML', 'BODY', 'NAV' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[52][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[53][self::OL]' => array( 'HTML', 'BODY', 'OL' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[54][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[55][self::PRE]' => array( 'HTML', 'BODY', 'PRE' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[56][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[57][self::SEARCH]' => array( 'HTML', 'BODY', 'SEARCH' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[58][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[59][self::SECTION]' => array( 'HTML', 'BODY', 'SECTION' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[60][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[61][self::TABLE]' => array( 'HTML', 'BODY', 'TABLE' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[62][self::P]' => array( 'HTML', 'BODY', 'P' ),
					'/*[1][self::HTML]/*[2][self::BODY]/*[63][self::UL]' => array( 'HTML', 'BODY', 'UL' ),
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
