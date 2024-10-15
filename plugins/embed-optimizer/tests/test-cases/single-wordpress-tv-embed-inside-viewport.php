<?php
return array(
	'set_up'   => static function ( Test_Embed_Optimizer_Optimization_Detective $test_case ): void {
		$rect = array(
			'width'  => 500.1,
			'height' => 500.2,
			'x'      => 100.3,
			'y'      => 100.4,
			'top'    => 0.1,
			'right'  => 0.2,
			'bottom' => 0.3,
			'left'   => 0.4,
		);
		$test_case->populate_url_metrics(
			array(
				array(
					'xpath'                     => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]/*[1][self::DIV]',
					'isLCP'                     => true,
					'intersectionRatio'         => 1,
					'resizedBoundingClientRect' => array_merge( $rect, array( 'height' => 500 ) ),
				),
			),
			false
		);
	},
	'buffer'   => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
			</head>
			<body>
				<figure class="wp-block-embed is-type-video is-provider-wordpress-tv wp-block-embed-wordpress-tv wp-embed-aspect-16-9 wp-has-aspect-ratio">
					<div class="wp-block-embed__wrapper">
						<iframe title="VideoPress Video Player" aria-label=\'VideoPress Video Player\' width=\'750\' height=\'422\' src=\'https://video.wordpress.com/embed/vaWm9zO6?hd=1&amp;cover=1\' frameborder=\'0\' allowfullscreen allow=\'clipboard-write\'></iframe>
						<script src=\'https://v0.wordpress.com/js/next/videopress-iframe.js?m=1674852142\'></script>
					</div>
				</figure>
			</body>
		</html>
	',
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
				<style>
				@media (max-width: 480px) { #embed-optimizer-a7659db28ecaa36ddee6ae66857dabd8 { min-height: 500px; } }
				@media (min-width: 481px) and (max-width: 600px) { #embed-optimizer-a7659db28ecaa36ddee6ae66857dabd8 { min-height: 500px; } }
				@media (min-width: 601px) and (max-width: 782px) { #embed-optimizer-a7659db28ecaa36ddee6ae66857dabd8 { min-height: 500px; } }
				@media (min-width: 783px) { #embed-optimizer-a7659db28ecaa36ddee6ae66857dabd8 { min-height: 500px; } }
				</style>
				<link data-od-added-tag rel="preconnect" href="https://video.wordpress.com">
				<link data-od-added-tag rel="preconnect" href="https://public-api.wordpress.com">
				<link data-od-added-tag rel="preconnect" href="https://videos.files.wordpress.com">
				<link data-od-added-tag rel="preconnect" href="https://v0.wordpress.com">
			</head>
			<body>
				<figure data-od-added-id id="embed-optimizer-a7659db28ecaa36ddee6ae66857dabd8" class="wp-block-embed is-type-video is-provider-wordpress-tv wp-block-embed-wordpress-tv wp-embed-aspect-16-9 wp-has-aspect-ratio">
					<div data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]/*[1][self::DIV]" class="wp-block-embed__wrapper">
						<iframe title="VideoPress Video Player" aria-label=\'VideoPress Video Player\' width=\'750\' height=\'422\' src=\'https://video.wordpress.com/embed/vaWm9zO6?hd=1&amp;cover=1\' frameborder=\'0\' allowfullscreen allow=\'clipboard-write\'></iframe>
						<script src=\'https://v0.wordpress.com/js/next/videopress-iframe.js?m=1674852142\'></script>
					</div>
				</figure>
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
