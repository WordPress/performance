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

		$outside_viewport_rect = array_merge(
			$test_case->get_sample_dom_rect(),
			array(
				'top' => 100000,
			)
		);

		foreach ( $breakpoint_max_widths as $non_desktop_viewport_width ) {
			OD_URL_Metrics_Post_Type::store_url_metric(
				od_get_url_metrics_slug( od_get_normalized_query_vars() ),
				$test_case->get_sample_url_metric(
					array(
						'viewport_width' => $non_desktop_viewport_width,
						'elements'       => array(
							array(
								'xpath'              => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::DIV]',
								'isLCP'              => false,
								'intersectionRatio'  => 0.0,
								'intersectionRect'   => $outside_viewport_rect,
								'boundingClientRect' => $outside_viewport_rect,
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
				<p>Pretend this is a super long paragraph that pushes the next div out of the initial viewport.</p>
				<div style="background-image:url(https://example.com/foo-bg.jpg); width:100%; height: 200px;">This is so background!</div>
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
				<p>Pretend this is a super long paragraph that pushes the next div out of the initial viewport.</p>
				<div data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[2][self::DIV]" style="background-image:url(https://example.com/foo-bg.jpg); width:100%; height: 200px;">This is so background!</div>
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
