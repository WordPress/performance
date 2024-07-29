<?php
return array(
	'set_up'   => static function ( Test_Image_Prioritizer_Helper $test_case ): void {
		$slug = od_get_url_metrics_slug( od_get_normalized_query_vars() );
		$sample_size = od_get_url_metrics_breakpoint_sample_size();

		// Only populate the largest viewport group.
		for ( $i = 0; $i < $sample_size; $i++ ) {
			OD_URL_Metrics_Post_Type::store_url_metric(
				$slug,
				$test_case->get_sample_url_metric(
					array(
						'viewport_width' => 1000,
						'elements'       => array(
							array(
								'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
								'isLCP' => true,
							),
							array(
								'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
								'isLCP' => false,
							),
						),
					)
				)
			);
		}
	},
	'buffer'   => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
			</head>
			<body>
				<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy">
				<img src="https://example.com/bar.jpg" alt="Bar" width="10" height="10" loading="lazy" fetchpriority="high">
			</body>
		</html>
	',
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/foo.jpg" media="screen and (min-width: 783px)">
			</head>
			<body>
				<img data-od-removed-loading="lazy" data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" >
				<img data-od-removed-fetchpriority="high" data-od-removed-loading="lazy" data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]" src="https://example.com/bar.jpg" alt="Bar" width="10" height="10"  >
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
