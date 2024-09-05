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

		foreach ( array_merge( $breakpoint_max_widths, array( 1600 ) ) as $i => $viewport_width ) {
			$elements = array(
				array(
					'isLCP' => false,
					'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
				),
				array(
					'isLCP' => false,
					'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
				),
				array(
					'isLCP' => false,
					'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[3][self::IMG]',
				),
				array(
					'isLCP' => false,
					'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[4][self::IMG]',
				),
			);
			$elements[ $i ]['isLCP'] = true;
			OD_URL_Metrics_Post_Type::store_url_metric(
				od_get_url_metrics_slug( od_get_normalized_query_vars() ),
				$test_case->get_sample_url_metric(
					array(
						'viewport_width' => $viewport_width,
						'elements'       => $elements,
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
				<img src="https://example.com/mobile-logo.png" alt="Mobile Logo" width="600" height="600">
				<img src="https://example.com/phablet-logo.png" alt="Phablet Logo" width="600" height="600" crossorigin>
				<img src="https://example.com/tablet-logo.png" alt="Tablet Logo" width="600" height="600" crossorigin="anonymous">
				<img src="https://example.net/desktop-logo.png" alt="Desktop Logo" width="600" height="600" crossorigin="use-credentials">
			</body>
		</html>
	',
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/mobile-logo.png" media="screen and (max-width: 480px)">
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/phablet-logo.png" crossorigin="anonymous" media="screen and (min-width: 481px) and (max-width: 600px)">
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/tablet-logo.png" crossorigin="anonymous" media="screen and (min-width: 601px) and (max-width: 782px)">
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.net/desktop-logo.png" crossorigin="use-credentials" media="screen and (min-width: 783px)">
			</head>
			<body>
				<img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]" src="https://example.com/mobile-logo.png" alt="Mobile Logo" width="600" height="600">
				<img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]" src="https://example.com/phablet-logo.png" alt="Phablet Logo" width="600" height="600" crossorigin>
				<img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[3][self::IMG]" src="https://example.com/tablet-logo.png" alt="Tablet Logo" width="600" height="600" crossorigin="anonymous">
				<img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[4][self::IMG]" src="https://example.net/desktop-logo.png" alt="Desktop Logo" width="600" height="600" crossorigin="use-credentials">
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
