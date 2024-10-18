<?php

$full_url = '';

return array(
	'set_up'   => static function ( Test_Image_Prioritizer_Helper $test_case, WP_UnitTest_Factory $factory ) use ( &$full_url ): void {
		$breakpoint_max_widths = array( 480, 600, 782 );
		add_filter(
			'od_breakpoint_max_widths',
			static function () use ( $breakpoint_max_widths ) {
				return $breakpoint_max_widths;
			}
		);

		$element = array(
			'isLCP'              => false,
			'xpath'              => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::VIDEO]',
			'boundingClientRect' => $test_case->get_sample_dom_rect(),
			'intersectionRatio'  => 1.0,
		);

		foreach ( $breakpoint_max_widths as $non_desktop_viewport_width ) {
			OD_URL_Metrics_Post_Type::store_url_metric(
				od_get_url_metrics_slug( od_get_normalized_query_vars() ),
				$test_case->get_sample_url_metric(
					array(
						'viewport_width' => $non_desktop_viewport_width,
						'elements'       => array( $element ),
					)
				)
			);
		}

		$attachment_id = $factory->attachment->create_object(
			DIR_TESTDATA . '/images/33772.jpg',
			0,
			array(
				'post_mime_type' => 'image/jpeg',
				'post_excerpt'   => 'A sample caption',
			)
		);

		wp_generate_attachment_metadata( $attachment_id, DIR_TESTDATA . '/images/33772.jpg' );

		$full_url = wp_get_attachment_url( $attachment_id );
	},
	'buffer'   => static function () use ( &$full_url ) {
		return <<<HTML
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
			</head>
			<body>
				<video class="desktop" poster="$full_url" width="1200" height="500" crossorigin>
					<source src="https://example.com/header.webm" type="video/webm">
					<source src="https://example.com/header.mp4" type="video/mp4">
				</video>
			</body>
		</html>
HTML;
	},
	'expected' => static function () use ( &$full_url ) {
		return <<<HTML
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
			</head>
			<body>
				<video data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::VIDEO]" class="desktop" poster="$full_url" width="1200" height="500" crossorigin>
					<source src="https://example.com/header.webm" type="video/webm">
					<source src="https://example.com/header.mp4" type="video/mp4">
				</video>
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
HTML;
	},
);
