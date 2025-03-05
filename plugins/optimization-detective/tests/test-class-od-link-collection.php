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
							'type'           => 'image/jpeg',
						),
					),
				),
				'expected_html'   => '
					<link data-od-added-tag rel="preload" href="https://example.com/foo.jpg" imagesrcset="https://example.com/foo-400.jpg 400w, https://example.com/foo-800.jpg 800w" imagesizes="100vw" crossorigin="anonymous" fetchpriority="high" as="image" media="screen" integrity="sha384-oqVuAfXRKap7fdgcCY5uykM6+R9GqQ8K/uxy9rx7HNQlGYl1kPzQho1wx4JwY8wC" referrerpolicy="origin" type="image/jpeg">
				',
				'expected_header' => 'Link: <https://example.com/foo.jpg>; rel="preload"; imagesrcset="https://example.com/foo-400.jpg 400w, https://example.com/foo-800.jpg 800w"; imagesizes="100vw"; crossorigin="anonymous"; fetchpriority="high"; as="image"; media="screen"; integrity="sha384-oqVuAfXRKap7fdgcCY5uykM6+R9GqQ8K/uxy9rx7HNQlGYl1kPzQho1wx4JwY8wC"; referrerpolicy="origin"; type="image/jpeg"',
				'expected_count'  => 1,
				'error'           => '',
			),
			'preload_imagesrcset_without_href'           => array(
				'links_args'      => array(
					array(
						array(
							'rel'         => 'preload',
							'imagesrcset' => 'https://example.com/foo-400.jpg 400w, https://example.com/foo-800.jpg 800w',
							'imagesizes'  => '(max-width: 600px) 480px, 800px',
							'as'          => 'image',
							'media'       => 'screen',
						),
					),
				),
				'expected_html'   => '
					<link data-od-added-tag rel="preload" imagesrcset="https://example.com/foo-400.jpg 400w, https://example.com/foo-800.jpg 800w" imagesizes="(max-width: 600px) 480px, 800px" as="image" media="screen">
				',
				'expected_header' => 'Link: <about:blank>; rel="preload"; imagesrcset="https://example.com/foo-400.jpg 400w, https://example.com/foo-800.jpg 800w"; imagesizes="(max-width: 600px) 480px, 800px"; as="image"; media="screen"',
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
					<link data-od-added-tag rel="preload" href="https://example.com/foo.jpg" crossorigin="anonymous" fetchpriority="high" as="image" media="screen and (width &lt;= 100px)">
				',
				'expected_header' => 'Link: <https://example.com/foo.jpg>; rel="preload"; crossorigin="anonymous"; fetchpriority="high"; as="image"; media="screen and (width <= 100px)"',
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
					<link data-od-added-tag rel="preload" href="https://example.com/foo.jpg" crossorigin="anonymous" fetchpriority="high" as="image" media="screen and (100px &lt; width &lt;= 200px)">
				',
				'expected_header' => 'Link: <https://example.com/foo.jpg>; rel="preload"; crossorigin="anonymous"; fetchpriority="high"; as="image"; media="screen and (100px < width <= 200px)"',
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
						200,
						300,
					),
				),
				'expected_html'   => '
					<link data-od-added-tag rel="preload" href="https://example.com/bar.jpg" as="image" media="screen">
					<link data-od-added-tag rel="preload" href="https://example.com/foo.jpg" crossorigin="anonymous" fetchpriority="high" as="image" media="screen and (100px &lt; width &lt;= 300px)">
				',
				'expected_header' => 'Link: <https://example.com/bar.jpg>; rel="preload"; as="image"; media="screen", <https://example.com/foo.jpg>; rel="preload"; crossorigin="anonymous"; fetchpriority="high"; as="image"; media="screen and (100px < width <= 300px)"',
				'expected_count'  => 3,
				'error'           => '',
			),
			'multiple_preloads_merged_full_range'        => array(
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
						800,
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
						800,
						null,
					),
				),
				'expected_html'   => '
					<link data-od-added-tag rel="preload" href="https://example.com/foo.jpg" crossorigin="anonymous" fetchpriority="high" as="image" media="screen">
				',
				'expected_header' => 'Link: <https://example.com/foo.jpg>; rel="preload"; crossorigin="anonymous"; fetchpriority="high"; as="image"; media="screen"',
				'expected_count'  => 2,
				'error'           => '',
			),
			'preconnect_with_min_max_viewport_widths'    => array(
				'links_args'      => array(
					array(
						array(
							'rel'  => 'preconnect',
							'href' => 'https://youtube.com/',
						),
						200,
						300,
					),
				),
				'expected_html'   => '
					<link data-od-added-tag rel="preconnect" href="https://youtube.com/" media="(200px &lt; width &lt;= 300px)">
				',
				'expected_header' => 'Link: <https://youtube.com/>; rel="preconnect"; media="(200px < width <= 300px)"',
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
						200,
						300,
					),
				),
				'expected_html'   => '
					<link data-od-added-tag rel="preconnect" href="https://youtube.com/" media="tty and (200px &lt; width &lt;= 300px)">
				',
				'expected_header' => 'Link: <https://youtube.com/>; rel="preconnect"; media="tty and (200px < width <= 300px)"',
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
				'expected_header' => 'Link: <https://example.com/bar.jpg>; rel="preload"; as="image"; fetchpriority="high"; imagesrcset="https://example.com/%22bar%22-480w.jpg 480w, https://example.com/%22bar%22-800w.jpg 800w"; imagesizes="(max-width: 600px) 480px, 800px"; crossorigin="anonymous"',
				'expected_count'  => 1,
				'error'           => '',
			),
			'preload_mime_with_quotes'                   => array(
				'links_args'      => array(
					array(
						array(
							'rel'  => 'preload',
							'href' => 'https://example.com/bar.webm',
							'as'   => 'video',
							'type' => 'video/webm; codecs="vp8, vorbis"',
						),
					),
				),
				'expected_html'   => '
					<link data-od-added-tag rel="preload" href="https://example.com/bar.webm" as="video" type="video/webm; codecs=&quot;vp8, vorbis&quot;">
				',
				'expected_header' => 'Link: <https://example.com/bar.webm>; rel="preload"; as="video"; type="video/webm; codecs=\"vp8, vorbis\""',
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
			'bad_rel'                                    => array(
				'links_args'      => array(
					array(
						array(
							'rel'  => 123,
							'href' => 'https://example.com/foo-400.jpg',
						),
					),
				),
				'expected_html'   => '',
				'expected_header' => '',
				'expected_count'  => 0,
				'error'           => 'Link attributes must be strings.',
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
			'international_domain_name'                  => array(
				'links_args'      => array(
					array(
						array(
							'rel'  => 'preload',
							'href' => 'https://例.example.com/תמונה.jpg',
							'as'   => 'image',
						),
					),
				),
				'expected_html'   => '
					<link data-od-added-tag rel="preload" href="https://例.example.com/תמונה.jpg" as="image">
				',
				'expected_header' => 'Link: <https://%E4%BE%8B.example.com/%D7%AA%D7%9E%D7%95%D7%A0%D7%94.jpg>; rel="preload"; as="image"',
				'expected_count'  => 1,
				'error'           => '',
			),
			'non_ascii_path'                             => array(
				'links_args'      => array(
					array(
						array(
							'rel'  => 'preload',
							'href' => 'https://example.com/חנות/תמונה.jpg',
							'as'   => 'image',
						),
					),
				),
				'expected_html'   => '
					<link data-od-added-tag rel="preload" href="https://example.com/חנות/תמונה.jpg" as="image">
				',
				'expected_header' => 'Link: <https://example.com/%D7%97%D7%A0%D7%95%D7%AA/%D7%AA%D7%9E%D7%95%D7%A0%D7%94.jpg>; rel="preload"; as="image"',
				'expected_count'  => 1,
				'error'           => '',
			),
			'non_ascii_srcset'                           => array(
				'links_args'      => array(
					array(
						array(
							'href'        => 'https://example.com/wp-content/uploads/2025/02/البيسون-1024x668-jpg.webp',
							'rel'         => 'preload',
							'as'          => 'image',
							'imagesizes'  => '(width <= 480px) 316px, (480px < width <= 600px) 489px, (600px < width <= 782px) 644px, (782px < width) 644px',
							'imagesrcset' => 'https://example.com/wp-content/uploads/2025/02/البيسون-1024x668-jpg.webp 1024w, https://example.com/wp-content/uploads/2025/02/البيسون-300x196-jpg.webp 300w, https://example.com/wp-content/uploads/2025/02/البيسون-768x501-jpg.webp 768w, https://example.com/wp-content/uploads/2025/02/البيسون-1536x1002-jpg.webp 1536w, https://example.com/wp-content/uploads/2025/02/البيسون-2048x1336-jpg.webp 2048w',
						),
					),
				),
				'expected_html'   => '
					<link data-od-added-tag href="https://example.com/wp-content/uploads/2025/02/البيسون-1024x668-jpg.webp" rel="preload" as="image" imagesizes="(width &lt;= 480px) 316px, (480px &lt; width &lt;= 600px) 489px, (600px &lt; width &lt;= 782px) 644px, (782px &lt; width) 644px" imagesrcset="https://example.com/wp-content/uploads/2025/02/البيسون-1024x668-jpg.webp 1024w, https://example.com/wp-content/uploads/2025/02/البيسون-300x196-jpg.webp 300w, https://example.com/wp-content/uploads/2025/02/البيسون-768x501-jpg.webp 768w, https://example.com/wp-content/uploads/2025/02/البيسون-1536x1002-jpg.webp 1536w, https://example.com/wp-content/uploads/2025/02/البيسون-2048x1336-jpg.webp 2048w">
				',
				'expected_header' => 'Link: <https://example.com/wp-content/uploads/2025/02/%D8%A7%D9%84%D8%A8%D9%8A%D8%B3%D9%88%D9%86-1024x668-jpg.webp>; rel="preload"; as="image"; imagesizes="(width <= 480px) 316px, (480px < width <= 600px) 489px, (600px < width <= 782px) 644px, (782px < width) 644px"; imagesrcset="https://example.com/wp-content/uploads/2025/02/%D8%A7%D9%84%D8%A8%D9%8A%D8%B3%D9%88%D9%86-1024x668-jpg.webp 1024w, https://example.com/wp-content/uploads/2025/02/%D8%A7%D9%84%D8%A8%D9%8A%D8%B3%D9%88%D9%86-300x196-jpg.webp 300w, https://example.com/wp-content/uploads/2025/02/%D8%A7%D9%84%D8%A8%D9%8A%D8%B3%D9%88%D9%86-768x501-jpg.webp 768w, https://example.com/wp-content/uploads/2025/02/%D8%A7%D9%84%D8%A8%D9%8A%D8%B3%D9%88%D9%86-1536x1002-jpg.webp 1536w, https://example.com/wp-content/uploads/2025/02/%D8%A7%D9%84%D8%A8%D9%8A%D8%B3%D9%88%D9%86-2048x1336-jpg.webp 2048w"',
				'expected_count'  => 1,
				'error'           => '',
			),
			'percent-in-path'                            => array(
				'links_args'      => array(
					array(
						array(
							'rel'  => 'preload',
							'href' => 'https://example.com/100%25-one-hundred-percent.png?a[1]=2',
							'as'   => 'image',
						),
					),
				),
				'expected_html'   => '
					<link data-od-added-tag rel="preload" href="https://example.com/100%25-one-hundred-percent.png?a[1]=2" as="image">
				',
				'expected_header' => 'Link: <https://example.com/100%25-one-hundred-percent.png?a%5B1%5D=2>; rel="preload"; as="image"',
				'expected_count'  => 1,
				'error'           => '',
			),
			'multisite_subdirectory_non_ascii'           => array(
				'links_args'      => array(
					array(
						array(
							'rel'  => 'preload',
							'href' => 'https://example.com/חנות/wp-content/uploads/2025/01/example.jpg?ver=1+2',
							'as'   => 'image',
						),
					),
				),
				'expected_html'   => '
					<link data-od-added-tag rel="preload" href="https://example.com/חנות/wp-content/uploads/2025/01/example.jpg?ver=1+2" as="image">
				',
				'expected_header' => 'Link: <https://example.com/%D7%97%D7%A0%D7%95%D7%AA/wp-content/uploads/2025/01/example.jpg?ver=1+2>; rel="preload"; as="image"',
				'expected_count'  => 1,
				'error'           => '',
			),
		);
	}

	/**
	 * Tests add_link.
	 *
	 * @covers ::add_link
	 * @covers ::get_html
	 * @covers ::get_prepared_links
	 * @covers ::merge_consecutive_links
	 * @covers ::get_response_header
	 * @covers ::encode_url_for_response_header
	 * @covers ::count
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

		$this->assertNull( $collection->get_response_header() );

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
