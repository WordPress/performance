<?php
/**
 * Tests for optimization-detective class OD_Link_Collection.
 *
 * @package optimization-detective
 *
 * @coversDefaultClass OD_Link_Collection
 */
class Test_OD_Link_Collection extends WP_UnitTestCase {

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_to_test_add_link(): array {
		return array(
			'preload_without_min_max_viewport_widths'    => array(
				'links_args'    => array(
					array(
						array(
							'rel'            => 'preload',
							'href'           => 'https://example.com/foo.jpg',
							'imagesrcset'    => 'https://example.com/foo-400.jpg 400w, https://example.com/foo-800.jpg 800w',
							'imagesizes'     => '100vw',
							'crossorigin'    => 'anonymous',
							'fetchpriority'  => 'high',
							'as'             => 'image',
							'media'          => 'screen',
							'integrity'      => 'sha384-oqVuAfXRKap7fdgcCY5uykM6+R9GqQ8K/uxy9rx7HNQlGYl1kPzQho1wx4JwY8wC',
							'referrerpolicy' => 'origin',
						),
					),
				),
				'expected_html' => '
					<link data-od-added-tag rel="preload" href="https://example.com/foo.jpg" imagesrcset="https://example.com/foo-400.jpg 400w, https://example.com/foo-800.jpg 800w" imagesizes="100vw" crossorigin="anonymous" fetchpriority="high" as="image" media="screen" integrity="sha384-oqVuAfXRKap7fdgcCY5uykM6+R9GqQ8K/uxy9rx7HNQlGYl1kPzQho1wx4JwY8wC" referrerpolicy="origin">
				',
				'error'         => '',
			),
			'preload_with_min0_max_viewport_widths'      => array(
				'links_args'    => array(
					array(
						array(
							'rel'           => 'preload',
							'href'          => 'https://example.com/foo.jpg',
							'crossorigin'   => 'anonymous',
							'fetchpriority' => 'high',
							'as'            => 'image',
							'media'         => 'screen',
						),
						0,
						100,
					),
				),
				'expected_html' => '
					<link data-od-added-tag rel="preload" href="https://example.com/foo.jpg" crossorigin="anonymous" fetchpriority="high" as="image" media="screen and (max-width: 100px)">
				',
				'error'         => '',
			),
			'preload_with_min_max_viewport_widths'       => array(
				'links_args'    => array(
					array(
						array(
							'rel'           => 'preload',
							'href'          => 'https://example.com/foo.jpg',
							'crossorigin'   => 'anonymous',
							'fetchpriority' => 'high',
							'as'            => 'image',
							'media'         => 'screen',
						),
						100,
						200,
					),
				),
				'expected_html' => '
					<link data-od-added-tag rel="preload" href="https://example.com/foo.jpg" crossorigin="anonymous" fetchpriority="high" as="image" media="screen and (min-width: 100px) and (max-width: 200px)">
				',
				'error'         => '',
			),
			'multiple_preloads_merged'                   => array(
				'links_args'    => array(
					array(
						array(
							'rel'           => 'preload',
							'href'          => 'https://example.com/foo.jpg',
							'crossorigin'   => 'anonymous',
							'fetchpriority' => 'high',
							'as'            => 'image',
							'media'         => 'screen',
						),
						100,
						200,
					),
					array(
						array(
							'rel'   => 'preload',
							'href'  => 'https://example.com/bar.jpg',
							'as'    => 'image',
							'media' => 'screen',
						),
					),
					array(
						array(
							'rel'           => 'preload',
							'href'          => 'https://example.com/foo.jpg',
							'crossorigin'   => 'anonymous',
							'fetchpriority' => 'high',
							'as'            => 'image',
							'media'         => 'screen',
						),
						201,
						300,
					),
				),
				'expected_html' => '
					<link data-od-added-tag rel="preload" href="https://example.com/bar.jpg" as="image" media="screen">
					<link data-od-added-tag rel="preload" href="https://example.com/foo.jpg" crossorigin="anonymous" fetchpriority="high" as="image" media="screen and (min-width: 100px) and (max-width: 300px)">
				',
				'error'         => '',
			),
			'preconnect_without_min_max_viewport_widths' => array(
				'links_args'    => array(
					array(
						array(
							'rel'  => 'preconnect',
							'href' => 'https://youtube.com/',
						),
					),
				),
				'expected_html' => '
					<link data-od-added-tag rel="preconnect" href="https://youtube.com/">
				',
				'error'         => '',
			),
			'bad_preconnect'                             => array(
				'links_args'    => array(
					array(
						array(
							'rel'         => 'preconnect',
							'imagesrcset' => 'https://example.com/foo-400.jpg 400w, https://example.com/foo-800.jpg 800w',
						),
					),
				),
				'expected_html' => '',
				'error'         => 'A preconnect link must include an href attribute.',
			),
			'bad_preload'                                => array(
				'links_args'    => array(
					array(
						array(
							'rel'  => 'preload',
							'href' => 'https://example.com/foo.jpg',
						),
					),
				),
				'expected_html' => '',
				'error'         => 'A preload link must include an as attribute.',
			),
			'missing_rel'                                => array(
				'links_args'    => array(
					array(
						array(
							'href' => 'https://example.com/foo.jpg',
						),
					),
				),
				'expected_html' => '',
				'error'         => 'The rel attribute must be provided.',
			),
			'missing_href_or_imagesrcset'                => array(
				'links_args'    => array(
					array(
						array(
							'rel' => 'preload',
							'as'  => 'image',
						),
					),
				),
				'expected_html' => '',
				'error'         => 'Either the href or imagesrcset attributes must be supplied.',
			),
			'bad_minimum_viewport_width'                 => array(
				'links_args'    => array(
					array(
						array(
							'rel'  => 'preconnect',
							'href' => 'https://example.com/',
						),
						-1,
					),
				),
				'expected_html' => '',
				'error'         => 'Minimum width must be at least zero.',
			),
			'bad_maximum_viewport_width'                 => array(
				'links_args'    => array(
					array(
						array(
							'rel'  => 'preconnect',
							'href' => 'https://example.com/',
						),
						0,
						-1,
					),
				),
				'expected_html' => '',
				'error'         => 'Maximum width must be greater than zero and greater than the minimum width.',
			),
			'bad_maximum_viewport_width2'                => array(
				'links_args'    => array(
					array(
						array(
							'rel'  => 'preconnect',
							'href' => 'https://example.com/',
						),
						200,
						100,
					),
				),
				'expected_html' => '',
				'error'         => 'Maximum width must be greater than zero and greater than the minimum width.',
			),
		);
	}

	/**
	 * Tests add_link.
	 *
	 * @covers ::add_link
	 * @covers ::get_html
	 *
	 * @dataProvider data_provider_to_test_add_link
	 *
	 * @param array<string, mixed> $links_args    Links args.
	 * @param string               $expected_html Expected HTML.
	 * @param string               $error         Error.
	 */
	public function test_add_link( array $links_args, string $expected_html, string $error = '' ): void {
		if ( $error ) {
			$this->expectException( InvalidArgumentException::class );
			$this->expectExceptionMessage( $error );
		}

		$collection = new OD_Link_Collection();
		foreach ( $links_args as $link_args ) {
			$collection->add_link( ...$link_args );
		}
		$this->assertSame(
			preg_replace( '/^\t+/m', '', ltrim( $expected_html ) ),
			$collection->get_html()
		);
	}
}
