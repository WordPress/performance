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
						array(
							'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]',
							'isLCP' => false,
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
				<!-- Example seen in the wild which caused a preload link to be added with a data: URL in the imagesrcset attribute. -->
				<img
					src="https://example.com/foo.webp"
					data-orig-src="https://example.com/foo.webp"
					width="1000"
					height="800"
					class="lazyload wp-image-1"
					srcset="data:image/svg+xml,%3Csvg%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20width%3D%271000%27%20height%3D%27800%27%20viewBox%3D%270%200%201000%20800%27%3E%3Crect%20width%3D%271000%27%20height%3D%27800%27%20fill-opacity%3D%220%22%2F%3E%3C%2Fsvg%3E"
					data-srcset="https://example.com/foo-200x91.webp 200w, https://example.com/foo-300x136.webp 300w, https://example.com/foo-400x181.webp 400w, https://example.com/foo-600x272.webp 600w, https://example.com/foo-768x348.webp 768w, https://example.com/foo-800x362.webp 800w, https://example.com/foo-1024x463.webp 1024w, https://example.com/foo-1200x543.webp 1200w, https://example.com/foo-1536x695.webp 1536w, https://example.com/foo.webp 1920w"
					data-sizes="auto"
					data-orig-sizes="(max-width: 767px) 100vw, 1920px"
				>

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
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/foo.webp" imagesrcset="data:image/svg+xml,%3Csvg%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20width%3D%271000%27%20height%3D%27800%27%20viewBox%3D%270%200%201000%20800%27%3E%3Crect%20width%3D%271000%27%20height%3D%27800%27%20fill-opacity%3D%220%22%2F%3E%3C%2Fsvg%3E" media="screen and (min-width: 783px)">
			</head>
			<body>
				<!-- Example seen in the wild which caused a preload link to be added with a data: URL in the imagesrcset attribute. -->
				<img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]"
					src="https://example.com/foo.webp"
					data-orig-src="https://example.com/foo.webp"
					width="1000"
					height="800"
					class="lazyload wp-image-1"
					srcset="data:image/svg+xml,%3Csvg%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20width%3D%271000%27%20height%3D%27800%27%20viewBox%3D%270%200%201000%20800%27%3E%3Crect%20width%3D%271000%27%20height%3D%27800%27%20fill-opacity%3D%220%22%2F%3E%3C%2Fsvg%3E"
					data-srcset="https://example.com/foo-200x91.webp 200w, https://example.com/foo-300x136.webp 300w, https://example.com/foo-400x181.webp 400w, https://example.com/foo-600x272.webp 600w, https://example.com/foo-768x348.webp 768w, https://example.com/foo-800x362.webp 800w, https://example.com/foo-1024x463.webp 1024w, https://example.com/foo-1200x543.webp 1200w, https://example.com/foo-1536x695.webp 1536w, https://example.com/foo.webp 1920w"
					data-sizes="auto"
					data-orig-sizes="(max-width: 767px) 100vw, 1920px"
				>

				<!-- Example with an adjoining NOSCRIPT > IMG tag which should be excluded from URL Metrics. -->
				<img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-src="https://example.com/bar.jpg" data-srcset="https://example.com/bar-large.jpg 1000w, https://example.com/bar-large.jpg 1000w" sizes="(max-width: 556px) 100vw, 556px" alt="Bar" class="attachment-large size-large wp-image-2 has-transparency lazyload" width="500" height="300">
				<noscript>
					<img data-od-unknown-tag data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[3][self::NOSCRIPT]/*[1][self::IMG]" src="https://example.com/bar.jpg" srcset="https://example.com/bar-large.jpg 1000w, https://example.com/bar-large.jpg 1000w" sizes="(max-width: 556px) 100vw, 556px" alt="Bar" class="attachment-large size-large wp-image-2 has-transparency lazyload" width="500" height="300">
				</noscript>
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
