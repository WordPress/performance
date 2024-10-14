<?php
return array(
	'set_up'   => static function ( Test_Image_Prioritizer_Helper $test_case ): void {
		$slug = od_get_url_metrics_slug( od_get_normalized_query_vars() );
		$sample_size = od_get_url_metrics_breakpoint_sample_size();
		$outside_viewport_rect = array_merge(
			$test_case->get_sample_dom_rect(),
			array(
				'top' => 100000,
			)
		);
		foreach ( array_merge( od_get_breakpoint_max_widths(), array( 1000 ) ) as $viewport_width ) {
			for ( $i = 0; $i < $sample_size; $i++ ) {
				OD_URL_Metrics_Post_Type::store_url_metric(
					$slug,
					$test_case->get_sample_url_metric(
						array(
							'viewport_width' => $viewport_width,
							'elements'       => array(
								array(
									'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::DIV]/*[1][self::IMG]',
									'isLCP' => true,
								),
								array(
									'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::DIV]/*[2][self::IMG]',
									'isLCP'             => false,
									'intersectionRatio' => 0.0, // Subsequent carousel slide.
								),
								array(
									'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::DIV]/*[3][self::IMG]',
									'isLCP'             => false,
									'intersectionRatio' => 0.0, // Subsequent carousel slide.
								),
								array(
									'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[3][self::IMG]',
									'isLCP'             => false,
									'intersectionRatio' => 0 === $i ? 0.5 : 0.0, // Make sure that the _max_ intersection ratio is considered.
								),
								// All are outside all initial viewports.
								array(
									'xpath'              => '/*[1][self::HTML]/*[2][self::BODY]/*[5][self::IMG]',
									'isLCP'              => false,
									'intersectionRatio'  => 0.0,
									'intersectionRect'   => $outside_viewport_rect,
									'boundingClientRect' => $outside_viewport_rect,
								),
								array(
									'xpath'              => '/*[1][self::HTML]/*[2][self::BODY]/*[6][self::IMG]',
									'isLCP'              => false,
									'intersectionRatio'  => 0.0,
									'intersectionRect'   => $outside_viewport_rect,
									'boundingClientRect' => $outside_viewport_rect,
								),
								array(
									'xpath'              => '/*[1][self::HTML]/*[2][self::BODY]/*[7][self::IMG]',
									'isLCP'              => false,
									'intersectionRatio'  => 0.0,
									'intersectionRect'   => $outside_viewport_rect,
									'boundingClientRect' => $outside_viewport_rect,
								),
							),
						)
					)
				);
			}
		}
	},
	'buffer'   => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
			</head>
			<body>
				<div class="carousel">
					<img src="https://example.com/foo1.jpg" alt="Foo" width="1200" height="800" fetchpriority="low" loading="lazy" srcset="https://example.com/foo1-480w.jpg 480w, https://example.com/foo1-800w.jpg 800w" sizes="(max-width: 600px) 480px, 800px" crossorigin="anonymous">
					<img src="https://example.com/foo2.jpg" alt="Foo" width="1200" height="800" loading="eager" srcset="https://example.com/foo2-480w.jpg 480w, https://example.com/foo2-800w.jpg 800w" sizes="(max-width: 600px) 480px, 800px">
					<img src="https://example.com/foo3.jpg" alt="Foo" width="1200" height="800" fetchpriority="high" srcset="https://example.com/foo3-480w.jpg 480w, https://example.com/foo3-800w.jpg 800w" sizes="(max-width: 600px) 480px, 800px">
				</div>
				<p>Pretend this is a super long paragraph that pushes the next image mostly out of the initial viewport.</p>
				<img src="https://example.com/bar.jpg" alt="Bar" width="10" height="10" fetchpriority="high" loading="lazy">
				<p>Now the following image is definitely outside the initial viewport.</p>
				<img src="https://example.com/baz.jpg" alt="Baz" width="10" height="10" fetchpriority="high">
				<img src="https://example.com/qux.jpg" alt="Qux" width="10" height="10" fetchpriority="high" loading="eager">
				<img src="https://example.com/quux.jpg" alt="Quux" width="10" height="10" loading="LAZY"><!-- This one is all good. -->
			</body>
		</html>
	',
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/foo1.jpg" imagesrcset="https://example.com/foo1-480w.jpg 480w, https://example.com/foo1-800w.jpg 800w" imagesizes="(max-width: 600px) 480px, 800px" crossorigin="anonymous" media="screen">
			</head>
			<body>
				<div class="carousel">
					<img data-od-removed-loading="lazy" data-od-replaced-fetchpriority="low" src="https://example.com/foo1.jpg" alt="Foo" width="1200" height="800" fetchpriority="high"  srcset="https://example.com/foo1-480w.jpg 480w, https://example.com/foo1-800w.jpg 800w" sizes="(max-width: 600px) 480px, 800px" crossorigin="anonymous">
					<img data-od-added-fetchpriority data-od-removed-loading="eager" fetchpriority="low" src="https://example.com/foo2.jpg" alt="Foo" width="1200" height="800"  srcset="https://example.com/foo2-480w.jpg 480w, https://example.com/foo2-800w.jpg 800w" sizes="(max-width: 600px) 480px, 800px">
					<img data-od-replaced-fetchpriority="high" src="https://example.com/foo3.jpg" alt="Foo" width="1200" height="800" fetchpriority="low" srcset="https://example.com/foo3-480w.jpg 480w, https://example.com/foo3-800w.jpg 800w" sizes="(max-width: 600px) 480px, 800px">
				</div>
				<p>Pretend this is a super long paragraph that pushes the next image mostly out of the initial viewport.</p>
				<img data-od-removed-fetchpriority="high" data-od-removed-loading="lazy" src="https://example.com/bar.jpg" alt="Bar" width="10" height="10"  >
				<p>Now the following image is definitely outside the initial viewport.</p>
				<img data-od-added-loading data-od-removed-fetchpriority="high" loading="lazy" src="https://example.com/baz.jpg" alt="Baz" width="10" height="10" >
				<img data-od-removed-fetchpriority="high" data-od-replaced-loading="eager" src="https://example.com/qux.jpg" alt="Qux" width="10" height="10"  loading="lazy">
				<img src="https://example.com/quux.jpg" alt="Quux" width="10" height="10" loading="LAZY"><!-- This one is all good. -->
			</body>
		</html>
	',
);
