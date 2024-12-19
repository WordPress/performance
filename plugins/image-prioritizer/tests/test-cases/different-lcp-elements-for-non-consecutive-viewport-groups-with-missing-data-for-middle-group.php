<?php
return array(
	// TODO: Eventually the images in this test should all be lazy-loaded, leaving the prioritization to the preload links.
	'set_up'   => static function ( Test_Image_Prioritizer_Helper $test_case ): void {
		OD_URL_Metrics_Post_Type::store_url_metric(
			od_get_url_metrics_slug( od_get_normalized_query_vars() ),
			$test_case->get_sample_url_metric(
				array(
					'viewport_width' => 400,
					'elements'       => array(
						array(
							'isLCP'             => true,
							'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
							'intersectionRatio' => 1.0,
						),
						array(
							'isLCP'             => false,
							'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
							'intersectionRatio' => 0.0,
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
							'isLCP'             => false,
							'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
							'intersectionRatio' => 0.0,
						),
						array(
							'isLCP'             => true,
							'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
							'intersectionRatio' => 1.0,
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
				<style>/* Never show mobile and desktop logos at the same time. */</style>
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
				<style>/* Never show mobile and desktop logos at the same time. */</style>
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/desktop-logo.png" media="screen and (min-width: 783px)">
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/mobile-logo.png" media="screen and (max-width: 480px)">
			</head>
			<body>
				<img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]" src="https://example.com/mobile-logo.png" alt="Mobile Logo" width="600" height="600">
				<img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]" src="https://example.com/desktop-logo.png" alt="Desktop Logo" width="600" height="600">
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
