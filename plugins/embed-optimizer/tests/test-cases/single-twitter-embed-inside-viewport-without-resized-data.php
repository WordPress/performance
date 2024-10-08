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
					'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]/*[1][self::DIV]',
					'isLCP'             => true,
					'intersectionRatio' => 1,
					// Intentionally omitting resizedBoundingClientRect here to test behavior when data isn't supplied.
				),
			)
		);
	},
	'buffer'   => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
			</head>
			<body>
				<figure class="wp-block-embed is-type-rich is-provider-twitter wp-block-embed-twitter">
					<div class="wp-block-embed__wrapper">
						<blockquote class="twitter-tweet" data-width="550" data-dnt="true"><p lang="en" dir="ltr">We want your feedback for the Privacy Sandbox 📨<br><br>Learn why your feedback is critical through real examples and learn how to provide it ↓ <a href="https://t.co/anGk6gWkbc">https://t.co/anGk6gWkbc</a></p>&mdash; Chrome for Developers (@ChromiumDev) <a href="https://twitter.com/ChromiumDev/status/1636796541368139777?ref_src=twsrc%5Etfw">March 17, 2023</a></blockquote>
						<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
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
				<link data-od-added-tag rel="preconnect" href="https://syndication.twitter.com">
				<link data-od-added-tag rel="preconnect" href="https://pbs.twimg.com">
			</head>
			<body>
				<figure class="wp-block-embed is-type-rich is-provider-twitter wp-block-embed-twitter">
					<div class="wp-block-embed__wrapper">
						<blockquote class="twitter-tweet" data-width="550" data-dnt="true"><p lang="en" dir="ltr">We want your feedback for the Privacy Sandbox 📨<br><br>Learn why your feedback is critical through real examples and learn how to provide it ↓ <a href="https://t.co/anGk6gWkbc">https://t.co/anGk6gWkbc</a></p>&mdash; Chrome for Developers (@ChromiumDev) <a href="https://twitter.com/ChromiumDev/status/1636796541368139777?ref_src=twsrc%5Etfw">March 17, 2023</a></blockquote>
						<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
					</div>
				</figure>
			</body>
		</html>
	',
);
