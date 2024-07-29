<?php
return array(
	'set_up'   => static function ( Test_Image_Prioritizer_Helper $test_case ): void {
		add_filter(
			'od_breakpoint_max_widths',
			static function () {
				return array( 480, 600, 782 );
			}
		);

		OD_URL_Metrics_Post_Type::store_url_metric(
			od_get_url_metrics_slug( od_get_normalized_query_vars() ),
			$test_case->get_sample_url_metric(
				array(
					'viewport_width' => 500,
					'elements'       => array(
						array(
							'isLCP' => true,
							'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
						),
						array(
							'isLCP' => false,
							'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
						),
					),
				)
			)
		);
		OD_URL_Metrics_Post_Type::store_url_metric(
			od_get_url_metrics_slug( od_get_normalized_query_vars() ),
			$test_case->get_sample_url_metric(
				array(
					'viewport_width' => 650,
					'elements'       => array(
						array(
							'isLCP' => false,
							'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
						),
						array(
							'isLCP' => false,
							'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
						),
					),
				)
			)
		);
		OD_URL_Metrics_Post_Type::store_url_metric(
			od_get_url_metrics_slug( od_get_normalized_query_vars() ),
			$test_case->get_sample_url_metric(
				array(
					'viewport_width' => 800,
					'elements'       => array(
						array(
							'isLCP' => false,
							'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
						),
						array(
							'isLCP' => true,
							'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
						),
					),
				)
			)
		);
		OD_URL_Metrics_Post_Type::store_url_metric(
			od_get_url_metrics_slug( od_get_normalized_query_vars() ),
			$test_case->get_sample_url_metric(
				array(
					'viewport_width' => 800,
					'elements'       => array(
						array(
							'isLCP' => false,
							'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
						),
						array(
							'isLCP' => false,
							'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
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
				<img src="https://example.com/mobile-logo.png" alt="Mobile Logo" width="600" height="600">
				<p>New paragraph since URL Metrics were captured!</p>
				<img data-od-unknown-tag src="https://example.com/desktop-logo.png" alt="Desktop Logo" width="600" height="600">
			</body>
		</html>
	',
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/mobile-logo.png" media="screen and (min-width: 481px) and (max-width: 600px)">
			</head>
			<body>
				<img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]" src="https://example.com/mobile-logo.png" alt="Mobile Logo" width="600" height="600">
				<p>New paragraph since URL Metrics were captured!</p>
				<img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[3][self::IMG]" data-od-unknown-tag src="https://example.com/desktop-logo.png" alt="Desktop Logo" width="600" height="600">
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
