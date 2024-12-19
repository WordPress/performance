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
					'viewport_width' => 375,
					'elements'       => array(
						array(
							'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
							'isLCP' => true,
						),
					),
				)
			)
		);

		OD_URL_Metrics_Post_Type::store_url_metric(
			od_get_url_metrics_slug( od_get_normalized_query_vars() ),
			$test_case->get_sample_url_metric(
				array(
					'viewport_width' => 1000,
					'elements'       => array(
						array(
							'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
							'isLCP' => true,
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
				<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" fetchpriority="high">
			</body>
		</html>
	',
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/foo.jpg" media="screen and (max-width: 480px)">
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/foo.jpg" media="screen and (min-width: 783px)">
			</head>
			<body>
				<img data-od-fetchpriority-already-added data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" fetchpriority="high">
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
