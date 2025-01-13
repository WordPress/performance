<?php
return array(
	'set_up'   => static function ( Test_Image_Prioritizer_Helper $test_case ): void {
		$slug = od_get_url_metrics_slug( od_get_normalized_query_vars() );

		// Populate one URL Metric so that none of the IMG elements are unknown.
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
				<script>/* custom lazy-loading */</script>
			</head>
			<body>
				<!-- Example with an adjoining NOSCRIPT > IMG tag which should be excluded from URL Metrics. -->
				<img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-src="https://example.com/bar.jpg" data-srcset="https://example.com/bar-large.jpg 1000w, https://example.com/bar-large.jpg 1000w" sizes="(max-width: 556px) 100vw, 556px" alt="Bar" class="attachment-large size-large wp-image-2 has-transparency lazyload" width="500" height="300">
				<noscript>
					<img src="https://example.com/bar.jpg" srcset="https://example.com/bar-large.jpg 1000w, https://example.com/bar-large.jpg 1000w" sizes="(max-width: 556px) 100vw, 556px" alt="Bar" class="attachment-large size-large wp-image-2 has-transparency lazyload" width="500" height="300">
				</noscript>
			</body>
		</html>
	',
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
				<script>/* custom lazy-loading */</script>
			</head>
			<body>
				<!-- Example with an adjoining NOSCRIPT > IMG tag which should be excluded from URL Metrics. -->
				<img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-src="https://example.com/bar.jpg" data-srcset="https://example.com/bar-large.jpg 1000w, https://example.com/bar-large.jpg 1000w" sizes="(max-width: 556px) 100vw, 556px" alt="Bar" class="attachment-large size-large wp-image-2 has-transparency lazyload" width="500" height="300">
				<noscript>
					<img src="https://example.com/bar.jpg" srcset="https://example.com/bar-large.jpg 1000w, https://example.com/bar-large.jpg 1000w" sizes="(max-width: 556px) 100vw, 556px" alt="Bar" class="attachment-large size-large wp-image-2 has-transparency lazyload" width="500" height="300">
				</noscript>
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
