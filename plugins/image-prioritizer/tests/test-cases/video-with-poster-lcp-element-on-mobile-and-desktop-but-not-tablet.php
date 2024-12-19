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

		OD_URL_Metrics_Post_Type::store_url_metric(
			od_get_url_metrics_slug( od_get_normalized_query_vars() ),
			$test_case->get_sample_url_metric(
				array(
					'viewport_width' => 480,
					'elements'       => array(
						array(
							'isLCP' => false,
							'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
						),
						array(
							'isLCP' => true,
							'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::VIDEO]',
						),
					),
				)
			)
		);

		foreach ( array( 600, 782 ) as $tablet_viewport_width ) {
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
						'viewport_width' => $tablet_viewport_width,
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
						array(
							'isLCP' => true,
							'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::VIDEO]',
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
				<img class="tablet" src="https://example.com/tablet-header.jpg" alt="Tablet header" width="800" height="600">
				<video class="not-tablet" poster="https://example.com/poster.jpg" width="1200" height="500" src="https://example.com/header.mp4"></video>
			</body>
		</html>
	',
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/poster.jpg" media="screen and (max-width: 480px)">
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/poster.jpg" media="screen and (min-width: 783px)">
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/tablet-header.jpg" media="screen and (min-width: 481px) and (max-width: 782px)">
			</head>
			<body>
				<img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]" class="tablet" src="https://example.com/tablet-header.jpg" alt="Tablet header" width="800" height="600">
				<video data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[2][self::VIDEO]" class="not-tablet" poster="https://example.com/poster.jpg" width="1200" height="500" src="https://example.com/header.mp4"></video>
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
