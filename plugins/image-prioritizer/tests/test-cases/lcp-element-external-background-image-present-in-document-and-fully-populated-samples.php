<?php
// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet
return array(
	'set_up'   => static function ( Test_Image_Prioritizer_Helper $test_case ): void {
		add_filter(
			'od_breakpoint_max_widths',
			static function () {
				return array( 480, 600, 782 );
			}
		);

		$slug = od_get_url_metrics_slug( od_get_normalized_query_vars() );
		$sample_size = od_get_url_metrics_breakpoint_sample_size();

		$bg_images = array(
			'https://example.com/mobile.jpg',
			'https://example.com/tablet.jpg',
			'https://example.com/phablet.jpg',
			'https://example.com/desktop.jpg',
		);

		// Fully populate all viewport groups.
		foreach ( array_merge( od_get_breakpoint_max_widths(), array( 1000 ) ) as $i => $viewport_width ) {
			for ( $j = 0; $j < $sample_size; $j++ ) {
				OD_URL_Metrics_Post_Type::store_url_metric(
					$slug,
					$test_case->get_sample_url_metric(
						array(
							'viewport_width' => $viewport_width,
							'elements'       => array(),
							'extended_root'  => array(
								'lcpElementExternalBackgroundImage' => array(
									'url'   => $bg_images[ $i ],
									'tag'   => 'HEADER',
									'id'    => 'masthead',
									'class' => 'banner',
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
				<link rel="stylesheet" href="/style.css">
			</head>
			<body>
				<header id="masthead" class="banner">
					<h1>Example</h1>
				</header>
			</body>
		</html>
	',
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
				<link rel="stylesheet" href="/style.css">
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/desktop.jpg" media="screen and (min-width: 783px)">
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/mobile.jpg" media="screen and (max-width: 480px)">
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/phablet.jpg" media="screen and (min-width: 601px) and (max-width: 782px)">
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/tablet.jpg" media="screen and (min-width: 481px) and (max-width: 600px)">
			</head>
			<body>
				<header id="masthead" class="banner">
					<h1>Example</h1>
				</header>
			</body>
		</html>
	',
);
