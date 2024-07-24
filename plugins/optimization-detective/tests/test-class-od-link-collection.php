<?php
/**
 * Tests for optimization-detective class OD_Link_Collection.
 *
 * @package optimization-detective
 *
 * phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
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
				'links_args'      => array(
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
				'expected_html'   => '
					<link data-od-added-tag rel="preload" href="https://example.com/foo.jpg" imagesrcset="https://example.com/foo-400.jpg 400w, https://example.com/foo-800.jpg 800w" imagesizes="100vw" crossorigin="anonymous" fetchpriority="high" as="image" media="screen" integrity="sha384-oqVuAfXRKap7fdgcCY5uykM6+R9GqQ8K/uxy9rx7HNQlGYl1kPzQho1wx4JwY8wC" referrerpolicy="origin">
				',
				'expected_header' => 'Link: <https://example.com/foo.jpg>; rel="preload"; imagesrcset="https://example.com/foo-400.jpg 400w, https://example.com/foo-800.jpg 800w"; imagesizes="100vw"; crossorigin="anonymous"; fetchpriority="high"; as="image"; media="screen"; integrity="sha384-oqVuAfXRKap7fdgcCY5uykM6+R9GqQ8K/uxy9rx7HNQlGYl1kPzQho1wx4JwY8wC"; referrerpolicy="origin"',
				'expected_count'  => 1,
				'error'           => '',
			),
			'preload_with_min0_max_viewport_widths'      => array(
				'links_args'      => array(
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
				'expected_html'   => '
					<link data-od-added-tag rel="preload" href="https://example.com/foo.jpg" crossorigin="anonymous" fetchpriority="high" as="image" media="screen and (max-width: 100px)">
				',
				'expected_header' => 'Link: <https://example.com/foo.jpg>; rel="preload"; crossorigin="anonymous"; fetchpriority="high"; as="image"; media="screen and (max-width: 100px)"',
				'expected_count'  => 1,
				'error'           => '',
			),
			'preload_with_min_max_viewport_widths'       => array(
				'links_args'      => array(
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
				'expected_html'   => '
					<link data-od-added-tag rel="preload" href="https://example.com/foo.jpg" crossorigin="anonymous" fetchpriority="high" as="image" media="screen and (min-width: 100px) and (max-width: 200px)">
				',
				'expected_header' => 'Link: <https://example.com/foo.jpg>; rel="preload"; crossorigin="anonymous"; fetchpriority="high"; as="image"; media="screen and (min-width: 100px) and (max-width: 200px)"',
				'expected_count'  => 1,
				'error'           => '',
			),
			'multiple_preloads_merged'                   => array(
				'links_args'      => array(
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
				'expected_html'   => '
					<link data-od-added-tag rel="preload" href="https://example.com/bar.jpg" as="image" media="screen">
					<link data-od-added-tag rel="preload" href="https://example.com/foo.jpg" crossorigin="anonymous" fetchpriority="high" as="image" media="screen and (min-width: 100px) and (max-width: 300px)">
				',
				'expected_header' => 'Link: <https://example.com/bar.jpg>; rel="preload"; as="image"; media="screen", <https://example.com/foo.jpg>; rel="preload"; crossorigin="anonymous"; fetchpriority="high"; as="image"; media="screen and (min-width: 100px) and (max-width: 300px)"',
				'expected_count'  => 3,
				'error'           => '',
			),
			'preconnect_with_min_max_viewport_widths'    => array(
				'links_args'      => array(
					array(
						array(
							'rel'  => 'preconnect',
							'href' => 'https://youtube.com/',
						),
						201,
						300,
					),
				),
				'expected_html'   => '
					<link data-od-added-tag rel="preconnect" href="https://youtube.com/" media="(min-width: 201px) and (max-width: 300px)">
				',
				'expected_header' => 'Link: <https://youtube.com/>; rel="preconnect"; media="(min-width: 201px) and (max-width: 300px)"',
				'expected_count'  => 1,
				'error'           => '',
			),
			'preconnect_with_min_max_viewport_widths_and_media' => array(
				'links_args'      => array(
					array(
						array(
							'rel'   => 'preconnect',
							'href'  => 'https://youtube.com/',
							'media' => 'tty',
						),
						201,
						300,
					),
				),
				'expected_html'   => '
					<link data-od-added-tag rel="preconnect" href="https://youtube.com/" media="tty and (min-width: 201px) and (max-width: 300px)">
				',
				'expected_header' => 'Link: <https://youtube.com/>; rel="preconnect"; media="tty and (min-width: 201px) and (max-width: 300px)"',
				'expected_count'  => 1,
				'error'           => '',
			),
			'preconnect_without_min_max_viewport_widths' => array(
				'links_args'      => array(
					array(
						array(
							'rel'  => 'preconnect',
							'href' => 'https://youtube.com/',
						),
					),
				),
				'expected_html'   => '
					<link data-od-added-tag rel="preconnect" href="https://youtube.com/">
				',
				'expected_header' => 'Link: <https://youtube.com/>; rel="preconnect"',
				'expected_count'  => 1,
				'error'           => '',
			),
			'print_stylesheet'                           => array(
				'links_args'      => array(
					array(
						array(
							'rel'   => 'stylesheet',
							'href'  => 'https://example.com/print.css',
							'media' => 'print',
						),
					),
				),
				'expected_html'   => '
					<link data-od-added-tag rel="stylesheet" href="https://example.com/print.css" media="print">
				',
				'expected_header' => 'Link: <https://example.com/print.css>; rel="stylesheet"; media="print"',
				'expected_count'  => 1,
				'error'           => '',
			),
			'escaped_links'                              => array(
				'links_args'      => array(
					array(
						array(
							'rel'           => 'preload',
							'href'          => 'https://example.com/bar.jpg',
							'as'            => 'image',
							'fetchpriority' => 'high',
							'imagesrcset'   => 'https://example.com/"bar"-480w.jpg 480w, https://example.com/"bar"-800w.jpg 800w',
							'imagesizes'    => '(max-width: 600px) 480px, 800px',
							'crossorigin'   => 'anonymous',
						),
					),
				),
				'expected_html'   => '
					<link data-od-added-tag rel="preload" href="https://example.com/bar.jpg" as="image" fetchpriority="high" imagesrcset="https://example.com/&quot;bar&quot;-480w.jpg 480w, https://example.com/&quot;bar&quot;-800w.jpg 800w" imagesizes="(max-width: 600px) 480px, 800px" crossorigin="anonymous">
				',
				'expected_header' => 'Link: <https://example.com/bar.jpg>; rel="preload"; as="image"; fetchpriority="high"; imagesrcset="https://example.com/\"bar\"-480w.jpg 480w, https://example.com/\"bar\"-800w.jpg 800w"; imagesizes="(max-width: 600px) 480px, 800px"; crossorigin="anonymous"',
				'expected_count'  => 1,
				'error'           => '',
			),
			'bad_preconnect'                             => array(
				'links_args'      => array(
					array(
						array(
							'rel'         => 'preconnect',
							'imagesrcset' => 'https://example.com/foo-400.jpg 400w, https://example.com/foo-800.jpg 800w',
						),
					),
				),
				'expected_html'   => '',
				'expected_header' => '',
				'expected_count'  => 0,
				'error'           => 'A link with rel=preconnect must include an &quot;href&quot; attribute.',
			),
			'bad_preload'                                => array(
				'links_args'      => array(
					array(
						array(
							'rel'  => 'preload',
							'href' => 'https://example.com/foo.jpg',
						),
					),
				),
				'expected_html'   => '',
				'expected_header' => '',
				'expected_count'  => 0,
				'error'           => 'A link with rel=preload must include an &quot;as&quot; attribute.',
			),
			'missing_rel'                                => array(
				'links_args'      => array(
					array(
						array(
							'href' => 'https://example.com/foo.jpg',
						),
					),
				),
				'expected_html'   => '',
				'expected_header' => '',
				'expected_count'  => 0,
				'error'           => 'The &quot;rel&quot; attribute must be provided.',
			),
			'missing_href_or_imagesrcset'                => array(
				'links_args'      => array(
					array(
						array(
							'rel' => 'preload',
							'as'  => 'image',
						),
					),
				),
				'expected_html'   => '',
				'expected_header' => '',
				'expected_count'  => 0,
				'error'           => 'Either the &quot;href&quot; or &quot;imagesrcset&quot; attribute must be supplied.',
			),
			'bad_minimum_viewport_width'                 => array(
				'links_args'      => array(
					array(
						array(
							'rel'  => 'preconnect',
							'href' => 'https://example.com/',
						),
						-1,
					),
				),
				'expected_html'   => '',
				'expected_header' => '',
				'expected_count'  => 0,
				'error'           => 'Minimum width must be at least zero.',
			),
			'bad_maximum_viewport_width'                 => array(
				'links_args'      => array(
					array(
						array(
							'rel'  => 'preconnect',
							'href' => 'https://example.com/',
						),
						0,
						-1,
					),
				),
				'expected_html'   => '',
				'expected_header' => '',
				'expected_count'  => 0,
				'error'           => 'Maximum width must be greater than zero and greater than the minimum width.',
			),
			'bad_maximum_viewport_width2'                => array(
				'links_args'      => array(
					array(
						array(
							'rel'  => 'preconnect',
							'href' => 'https://example.com/',
						),
						200,
						100,
					),
				),
				'expected_html'   => '',
				'expected_header' => '',
				'expected_count'  => 0,
				'error'           => 'Maximum width must be greater than zero and greater than the minimum width.',
			),
		);
	}

	/**
	 * Tests add_link.
	 *
	 * @covers ::add_link
	 * @covers ::get_html
	 * @covers ::get_response_header
	 *
	 * @dataProvider data_provider_to_test_add_link
	 *
	 * @param array<string, mixed> $links_args      Links args.
	 * @param string               $expected_html   Expected HTML.
	 * @param string               $expected_header Expected Link header.
	 * @param int                  $expected_count  Expected count of links.
	 * @param string               $error           Error.
	 */
	public function test_add_link( array $links_args, string $expected_html, string $expected_header, int $expected_count, string $error = '' ): void {
		if ( '' !== $error ) {
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

		$this->assertSame(
			$expected_header,
			$collection->get_response_header()
		);

		$this->assertCount( $expected_count, $collection );
	}
}
