<?php
return array(
	'set_up'   => static function ( Test_OD_Optimization $test_case ): void {
		$slug = od_get_url_metrics_slug( od_get_normalized_query_vars() );
		foreach ( array_merge( od_get_breakpoint_max_widths(), array( 1000 ) ) as $viewport_width ) {
			OD_URL_Metrics_Post_Type::store_url_metric(
				$slug,
				$test_case->get_validated_url_metric(
					$viewport_width,
					array(
						array(
							'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::VIDEO]',
							'isLCP' => true,
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
				<video width="620" controls poster="https://upload.wikimedia.org/wikipedia/commons/e/e8/Elephants_Dream_s5_both.jpg">
					<source src="https://archive.org/download/ElephantsDream/ed_hd.avi" type="video/avi" />
					<source src="https://archive.org/download/ElephantsDream/ed_1024_512kb.mp4" type="video/mp4" />
				</video>
			</body>
		</html>
	',
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
			</head>
			<body>
				<video data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::VIDEO]" width="620" controls poster="https://upload.wikimedia.org/wikipedia/commons/e/e8/Elephants_Dream_s5_both.jpg">
					<source src="https://archive.org/download/ElephantsDream/ed_hd.avi" type="video/avi" />
					<source src="https://archive.org/download/ElephantsDream/ed_1024_512kb.mp4" type="video/mp4" />
				</video>
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
