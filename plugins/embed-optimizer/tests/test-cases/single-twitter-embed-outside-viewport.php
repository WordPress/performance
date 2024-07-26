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
			</head>
			<body>
				<figure data-od-added-style style="min-height: 500px;" class="wp-block-embed is-type-rich is-provider-twitter wp-block-embed-twitter">
					<div class="wp-block-embed__wrapper">
						<blockquote class="twitter-tweet" data-width="550" data-dnt="true"><p lang="en" dir="ltr">We want your feedback for the Privacy Sandbox 📨<br><br>Learn why your feedback is critical through real examples and learn how to provide it ↓ <a href="https://t.co/anGk6gWkbc">https://t.co/anGk6gWkbc</a></p>&mdash; Chrome for Developers (@ChromiumDev) <a href="https://twitter.com/ChromiumDev/status/1636796541368139777?ref_src=twsrc%5Etfw">March 17, 2023</a></blockquote>
						<script data-od-added-type type="application/vnd.embed-optimizer.javascript" async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
					</div>
				</figure>
				<script type="module">/* const lazyEmbedsScripts ... */</script>
			</body>
		</html>
	',
);
