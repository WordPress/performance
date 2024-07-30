<?php
return array(
	'set_up'   => static function ( Test_Embed_Optimizer_Optimization_Detective $test_case ): void {
		$test_case->populate_url_metrics(
			array(
				array(
					'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]/*[1][self::DIV]',
					'isLCP'             => false,
					'intersectionRatio' => 0,
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
			</head>
			<body>
				<figure data-od-added-style style="min-height: 500px;" class="wp-block-embed is-type-video is-provider-wordpress-tv wp-block-embed-wordpress-tv wp-embed-aspect-16-9 wp-has-aspect-ratio">
					<div data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]/*[1][self::DIV]" class="wp-block-embed__wrapper">
						<iframe data-od-added-loading loading="lazy" title="VideoPress Video Player" aria-label=\'VideoPress Video Player\' width=\'750\' height=\'422\' src=\'https://video.wordpress.com/embed/vaWm9zO6?hd=1&amp;cover=1\' frameborder=\'0\' allowfullscreen allow=\'clipboard-write\'></iframe>
						<script data-od-added-type type="application/vnd.embed-optimizer.javascript" src=\'https://v0.wordpress.com/js/next/videopress-iframe.js?m=1674852142\'></script>
					</div>
				</figure>
				<script type="module">/* const lazyEmbedsScripts ... */</script>
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
