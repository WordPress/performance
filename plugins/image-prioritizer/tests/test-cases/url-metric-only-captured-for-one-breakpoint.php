<?php
return array(
	'set_up'   => static function ( Test_Image_Prioritizer_Helper $test_case ): void {
		OD_URL_Metrics_Post_Type::store_url_metric(
			od_get_url_metrics_slug( od_get_normalized_query_vars() ),
			$test_case->get_sample_url_metric(
				array(
					'viewport_width' => 400,
					'element'        => array(
						'isLCP' => true,
						'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
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
				<!--</head>-->
			</head>
			<!--</head>-->
			<body>
				<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800">
				<!--</body>-->
			</body>
			<!--</body>-->
		</html>
	',
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
				<!--</head>-->
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/foo.jpg" media="screen and (max-width: 480px)">
			</head>
			<!--</head>-->
			<body>
				<img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800">
				<!--</body>-->
				<script type="module">/* import detect ... */</script>
			</body>
			<!--</body>-->
		</html>
	',
);
