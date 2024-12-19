<?php
return array(
	'set_up'   => static function ( Test_Image_Prioritizer_Helper $test_case ): void {
		$slug = od_get_url_metrics_slug( od_get_normalized_query_vars() );
		$sample_size = od_get_url_metrics_breakpoint_sample_size();

		$get_dom_rect = static function ( $left, $top, $width, $height ) {
			$dom_rect = array(
				'top'    => $top,
				'left'   => $left,
				'width'  => $width,
				'height' => $height,
				'x'      => $left,
				'y'      => $top,
			);
			$dom_rect['bottom'] = $dom_rect['top'] + $height;
			$dom_rect['right'] = $dom_rect['left'] + $width;
			return $dom_rect;
		};

		$width = 10;
		$height = 10;
		$above_viewport_rect = $get_dom_rect( 0, -100, $width, $height );
		$left_of_viewport_rect = $get_dom_rect( -100, 0, $width, $height );
		$right_of_viewport_rect = $get_dom_rect( 10000000, 0, $width, $height );
		$below_viewport_rect = $get_dom_rect( 0, 1000000, $width, $height );

		foreach ( array_merge( od_get_breakpoint_max_widths(), array( 1000 ) ) as $viewport_width ) {
			for ( $i = 0; $i < $sample_size; $i++ ) {
				OD_URL_Metrics_Post_Type::store_url_metric(
					$slug,
					$test_case->get_sample_url_metric(
						array(
							'viewport_width' => $viewport_width,
							'elements'       => array(
								array(
									'xpath'              => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
									'isLCP'              => false,
									'intersectionRatio'  => 0.0,
									'intersectionRect'   => $above_viewport_rect,
									'boundingClientRect' => $above_viewport_rect,
								),
								array(
									'xpath'              => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
									'isLCP'              => false,
									'intersectionRatio'  => 0.0,
									'intersectionRect'   => $left_of_viewport_rect,
									'boundingClientRect' => $left_of_viewport_rect,
								),
								array(
									'xpath'              => '/*[1][self::HTML]/*[2][self::BODY]/*[3][self::IMG]',
									'isLCP'              => false,
									'intersectionRatio'  => 0.0,
									'intersectionRect'   => $right_of_viewport_rect,
									'boundingClientRect' => $right_of_viewport_rect,
								),
								array(
									'xpath'              => '/*[1][self::HTML]/*[2][self::BODY]/*[4][self::IMG]',
									'isLCP'              => false,
									'intersectionRatio'  => 0.0,
									'intersectionRect'   => $below_viewport_rect,
									'boundingClientRect' => $below_viewport_rect,
								),
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
			</head>
			<body>
				<!-- These three are all true for is_element_positioned_in_any_initial_viewport since their top is above the viewport height. -->
				<img src="https://example.com/bad-left-of-viewport.jpg" alt="Left of Viewport" width="10" height="10" fetchpriority="high">
				<img src="https://example.com/bad-above-viewport.jpg" alt="Above viewport" width="10" height="10" loading="lazy">
				<img src="https://example.com/bad-right-of-viewport.jpg" alt="Right of viewport" width="10" height="10">

				<!-- The only one that should get loading=lazy since user should be able to scroll to it. -->
				<img src="https://example.com/bad-below-viewport.jpg" alt="Below viewport" width="10" height="10">
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
				<!-- These three are all true for is_element_positioned_in_any_initial_viewport since their top is above the viewport height. -->
				<img data-od-replaced-fetchpriority="high" src="https://example.com/bad-left-of-viewport.jpg" alt="Left of Viewport" width="10" height="10" fetchpriority="low">
				<img data-od-added-fetchpriority data-od-removed-loading="lazy" fetchpriority="low" src="https://example.com/bad-above-viewport.jpg" alt="Above viewport" width="10" height="10" >
				<img data-od-added-fetchpriority fetchpriority="low" src="https://example.com/bad-right-of-viewport.jpg" alt="Right of viewport" width="10" height="10">

				<!-- The only one that should get loading=lazy since user should be able to scroll to it. -->
				<img data-od-added-loading loading="lazy" src="https://example.com/bad-below-viewport.jpg" alt="Below viewport" width="10" height="10">
			</body>
		</html>
	',
);
