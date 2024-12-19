<?php
return array(
	'set_up'   => static function ( Test_Image_Prioritizer_Helper $test_case ): void {
		$mobile_breakpoint  = 480;
		$tablet_breakpoint  = 600;
		$desktop_breakpoint = 782;
		add_filter(
			'od_breakpoint_max_widths',
			static function () use ( $mobile_breakpoint, $tablet_breakpoint ): array {
				return array( $mobile_breakpoint, $tablet_breakpoint );
			}
		);
		$sample_size = od_get_url_metrics_breakpoint_sample_size();

		$slug = od_get_url_metrics_slug( od_get_normalized_query_vars() );
		$div_index_to_viewport_width_mapping = array(
			0 => $desktop_breakpoint,
			1 => $tablet_breakpoint,
			2 => $mobile_breakpoint,
		);

		foreach ( $div_index_to_viewport_width_mapping as $div_index => $viewport_width ) {
			for ( $i = 0; $i < $sample_size; $i++ ) {
				OD_URL_Metrics_Post_Type::store_url_metric(
					$slug,
					$test_case->get_sample_url_metric(
						array(
							'viewport_width' => $viewport_width,
							'element'        => array(
								'xpath' => sprintf( '/*[1][self::HTML]/*[2][self::BODY]/*[%d][self::DIV]', $div_index + 1 ),
								'isLCP' => true,
							),
						)
					)
				);
			}
		}
	},
	'buffer'   => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
				<style>/* responsive styles to show only one div.header at a time... */</style>
			</head>
			<body>
				<div class="header desktop" style="background: red no-repeat center/80% url(\'https://example.com/desktop-bg.jpg\'); width:100%; height: 200px;">This is the desktop background!</div>
				<div class="header tablet" style=\'background-image:url( "https://example.com/tablet-bg.jpg" ); width:100%; height: 200px;\'>This is the tablet background!</div>
				<div class="header mobile" style="background-image:url(https://example.com/mobile-bg.jpg); width:100%; height: 200px;">This is the mobile background!</div>
			</body>
		</html>
	',
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
				<style>/* responsive styles to show only one div.header at a time... */</style>
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/desktop-bg.jpg" media="screen and (min-width: 601px)">
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/mobile-bg.jpg" media="screen and (max-width: 480px)">
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/tablet-bg.jpg" media="screen and (min-width: 481px) and (max-width: 600px)">
			</head>
			<body>
				<div class="header desktop" style="background: red no-repeat center/80% url(\'https://example.com/desktop-bg.jpg\'); width:100%; height: 200px;">This is the desktop background!</div>
				<div class="header tablet" style=\'background-image:url( "https://example.com/tablet-bg.jpg" ); width:100%; height: 200px;\'>This is the tablet background!</div>
				<div class="header mobile" style="background-image:url(https://example.com/mobile-bg.jpg); width:100%; height: 200px;">This is the mobile background!</div>
			</body>
		</html>
	',
);
