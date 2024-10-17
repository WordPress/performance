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

		foreach ( array_merge( $breakpoint_max_widths, array( 1000 ) ) as $viewport_width ) {
			OD_URL_Metrics_Post_Type::store_url_metric(
				od_get_url_metrics_slug( od_get_normalized_query_vars() ),
				$test_case->get_sample_url_metric(
					array(
						'viewport_width' => $viewport_width,
						'elements'       => array(
							array(
								'isLCP'              => true,
								'xpath'              => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::VIDEO]',
								'boundingClientRect' => $test_case->get_sample_dom_rect(),
								'intersectionRatio'  => 1.0,
							),
							array(
								'isLCP'              => false,
								'xpath'              => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::VIDEO]',
								'boundingClientRect' => $test_case->get_sample_dom_rect(),
								'intersectionRatio'  => 0.1,
							),
							array(
								'isLCP'              => false,
								'xpath'              => '/*[1][self::HTML]/*[2][self::BODY]/*[3][self::VIDEO]',
								'boundingClientRect' => $test_case->get_sample_dom_rect(),
								'intersectionRatio'  => 0.0,
							),
							array(
								'isLCP'              => false,
								'xpath'              => '/*[1][self::HTML]/*[2][self::BODY]/*[4][self::VIDEO]',
								'boundingClientRect' => $test_case->get_sample_dom_rect(),
								'intersectionRatio'  => 0.0,
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
				<video class="desktop" poster="https://example.com/poster1.jpg" width="1200" height="500" src="https://example.com/header1.mp4" preload="none" autoplay></video>
				<video class="desktop" poster="https://example.com/poster2.jpg" width="1200" height="500" src="https://example.com/header2.mp4" preload="auto" autoplay></video>
				<video class="desktop" poster="https://example.com/poster3.jpg" width="1200" height="500" src="https://example.com/header3.mp4" preload autoplay></video>
				<video class="desktop" poster="https://example.com/poster4.jpg" width="1200" height="500" src="https://example.com/header4.mp4" preload="metadata" autoplay></video>
				<video class="desktop" poster="https://example.com/poster5.jpg" width="1200" height="500" src="https://example.com/header5.mp4" autoplay></video>
			</body>
		</html>
	',
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/poster1.jpg" media="screen">
			</head>
			<body>
				<video data-od-replaced-preload="none" data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::VIDEO]" class="desktop" poster="https://example.com/poster1.jpg" width="1200" height="500" src="https://example.com/header1.mp4" preload="auto" autoplay></video>
				<video data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[2][self::VIDEO]" class="desktop" poster="https://example.com/poster2.jpg" width="1200" height="500" src="https://example.com/header2.mp4" preload="auto" autoplay></video>
				<video data-od-added-data-original-autoplay data-od-added-data-original-poster data-od-added-data-original-preload data-od-removed-autoplay data-od-removed-poster="https://example.com/poster3.jpg" data-od-replaced-class="desktop" data-od-replaced-preload data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[3][self::VIDEO]" data-original-autoplay data-original-poster="https://example.com/poster3.jpg" data-original-preload class="desktop od-lazy-video"  width="1200" height="500" src="https://example.com/header3.mp4" preload="none" ></video>
				<video data-od-added-data-original-autoplay data-od-added-data-original-poster data-od-added-data-original-preload data-od-removed-autoplay data-od-removed-poster="https://example.com/poster4.jpg" data-od-replaced-class="desktop" data-od-replaced-preload="metadata" data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[4][self::VIDEO]" data-original-autoplay data-original-poster="https://example.com/poster4.jpg" data-original-preload="metadata" class="desktop od-lazy-video"  width="1200" height="500" src="https://example.com/header4.mp4" preload="none" ></video>
				<video data-od-added-data-original-autoplay data-od-added-data-original-poster data-od-added-data-original-preload data-od-added-preload data-od-removed-autoplay data-od-removed-poster="https://example.com/poster5.jpg" data-od-replaced-class="desktop" data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[5][self::VIDEO]" data-original-autoplay data-original-poster="https://example.com/poster5.jpg" data-original-preload="default" preload="none" class="desktop od-lazy-video"  width="1200" height="500" src="https://example.com/header5.mp4" ></video>
				<script type="module">/* const lazyVideoObserver ... */</script>
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
