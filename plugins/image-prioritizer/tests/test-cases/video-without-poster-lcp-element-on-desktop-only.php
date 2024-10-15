<?php
return array(
	'set_up'   => static function ( Test_Image_Prioritizer_Helper $test_case ): void {
		$breakpoint_max_widths = array( 480, 600, 782 );

		add_filter(
			'od_breakpoint_max_widths',
			static function () use ( $breakpoint_max_widths ) {
				return $breakpoint_max_widths;
			}
		);

		foreach ( $breakpoint_max_widths as $non_desktop_viewport_width ) {
			$elements = array(
				array(
					'isLCP' => true,
					'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
				),
				array(
					'isLCP' => false,
					'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::VIDEO]',
				),
			);
			OD_URL_Metrics_Post_Type::store_url_metric(
				od_get_url_metrics_slug( od_get_normalized_query_vars() ),
				$test_case->get_sample_url_metric(
					array(
						'viewport_width' => $non_desktop_viewport_width,
						'elements'       => $elements,
					)
				)
			);
		}

		OD_URL_Metrics_Post_Type::store_url_metric(
			od_get_url_metrics_slug( od_get_normalized_query_vars() ),
			$test_case->get_sample_url_metric(
				array(
					'viewport_width' => 1000,
					'elements'       => array(
						array(
							'isLCP' => false,
							'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
						),
					),
				)
			)
		);
	},
	'buffer'   => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
			</head>
			<body>
				<img class="mobile" src="https://example.com/mobile-header.jpg" alt="Mobile Logo" width="800" height="600">
				<video class="desktop" width="1200" height="500">
					<source src="https://example.com/header.webm" type="video/webm">
					<source src="https://example.com/header.mp4" type="video/mp4">
				</video>
			</body>
		</html>
	',
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/mobile-header.jpg" media="screen and (max-width: 782px)">
			</head>
			<body>
				<img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]" class="mobile" src="https://example.com/mobile-header.jpg" alt="Mobile Logo" width="800" height="600">
				<video class="desktop" width="1200" height="500">
					<source src="https://example.com/header.webm" type="video/webm">
					<source src="https://example.com/header.mp4" type="video/mp4">
				</video>
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
