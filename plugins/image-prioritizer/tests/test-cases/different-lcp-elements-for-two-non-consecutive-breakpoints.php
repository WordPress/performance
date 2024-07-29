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
			$test_case->get_validated_url_metric(
				400,
				array(
					array(
						'isLCP' => true,
						'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
					),
					array(
						'isLCP' => false,
						'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
					),
				)
			)
		);
		OD_URL_Metrics_Post_Type::store_url_metric(
			od_get_url_metrics_slug( od_get_normalized_query_vars() ),
			$test_case->get_validated_url_metric(
				500,
				array(
					array(
						'isLCP' => false,
						'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
					),
					array(
						'isLCP' => false,
						'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
					),
				)
			)
		);
		OD_URL_Metrics_Post_Type::store_url_metric(
			od_get_url_metrics_slug( od_get_normalized_query_vars() ),
			$test_case->get_validated_url_metric(
				700,
				array(
					array(
						'isLCP' => false,
						'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
					),
					array(
						'isLCP' => true,
						'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
					),
				)
			)
		);
		OD_URL_Metrics_Post_Type::store_url_metric(
			od_get_url_metrics_slug( od_get_normalized_query_vars() ),
			$test_case->get_validated_url_metric(
				800,
				array(
					array(
						'isLCP' => false,
						'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
					),
					array(
						'isLCP' => false,
						'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
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
				<img src="https://example.com/desktop-logo.png" alt="Desktop Logo" width="600" height="600">
			</body>
		</html>
	',
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/mobile-logo.png" media="screen and (max-width: 480px)">
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/desktop-logo.png" media="screen and (min-width: 601px) and (max-width: 782px)">
			</head>
			<body>
				<img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]" src="https://example.com/mobile-logo.png" alt="Mobile Logo" width="600" height="600">
				<img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]" src="https://example.com/desktop-logo.png" alt="Desktop Logo" width="600" height="600">
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
