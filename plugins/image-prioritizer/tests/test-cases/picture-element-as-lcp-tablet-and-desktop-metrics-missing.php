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

		$slug = od_get_url_metrics_slug( od_get_normalized_query_vars() );
		$sample_size = od_get_url_metrics_breakpoint_sample_size();

		// Only populate the mobile and phablet viewport groups.
		foreach ( array( 480, 600 ) as $viewport_width ) {
			for ( $i = 0; $i < $sample_size; $i++ ) {
				OD_URL_Metrics_Post_Type::store_url_metric(
					$slug,
					$test_case->get_sample_url_metric(
						array(
							'viewport_width' => $viewport_width,
							'elements'       => array(
								array(
									'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::PICTURE]/*[3][self::IMG]',
									'isLCP' => true,
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
				<picture>
					<source type="image/avif" srcset="https://example.com/foo-300x225.avif 300w, https://example.com/foo-1024x768.avif 1024w, https://example.com/foo-768x576.avif 768w, https://example.com/foo-1536x1152.avif 1536w, https://example.com/foo-2048x1536.avif 2048w" sizes="(max-width: 600px) 480px, 800px">
					<source type="image/webp" srcset="https://example.com/foo-300x225.webp 300w, https://example.com/foo-1024x768.webp 1024w, https://example.com/foo-768x576.webp 768w, https://example.com/foo-1536x1152.webp 1536w, https://example.com/foo-2048x1536.webp 2048w" sizes="(max-width: 600px) 480px, 800px">
					<img fetchpriority="high" decoding="async" width="1200" height="800" src="https://example.com/foo.jpg" alt="Foo" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="(max-width: 600px) 480px, 800px">
				</picture>
			</body>
		</html>
	',
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" imagesrcset="https://example.com/foo-300x225.avif 300w, https://example.com/foo-1024x768.avif 1024w, https://example.com/foo-768x576.avif 768w, https://example.com/foo-1536x1152.avif 1536w, https://example.com/foo-2048x1536.avif 2048w" imagesizes="(max-width: 600px) 480px, 800px" type="image/avif" media="screen and (max-width: 600px)">
			</head>
			<body>
				<picture>
					<source type="image/avif" srcset="https://example.com/foo-300x225.avif 300w, https://example.com/foo-1024x768.avif 1024w, https://example.com/foo-768x576.avif 768w, https://example.com/foo-1536x1152.avif 1536w, https://example.com/foo-2048x1536.avif 2048w" sizes="(max-width: 600px) 480px, 800px">
					<source type="image/webp" srcset="https://example.com/foo-300x225.webp 300w, https://example.com/foo-1024x768.webp 1024w, https://example.com/foo-768x576.webp 768w, https://example.com/foo-1536x1152.webp 1536w, https://example.com/foo-2048x1536.webp 2048w" sizes="(max-width: 600px) 480px, 800px">
					<img data-od-removed-fetchpriority="high" data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::PICTURE]/*[3][self::IMG]"  decoding="async" width="1200" height="800" src="https://example.com/foo.jpg" alt="Foo" srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="(max-width: 600px) 480px, 800px">
				</picture>
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
