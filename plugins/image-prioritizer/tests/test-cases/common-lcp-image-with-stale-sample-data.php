<?php
return array(
	'set_up'   => static function ( Test_Image_Prioritizer_Helper $test_case ): void {
		$test_case->populate_url_metrics(
			array(
				array(
					'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]', // Note: This is intentionally not reflecting the IMG in the HTML below.
					'isLCP' => true,
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
				<script>/* Something injected with wp_body_open */</script>
				<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800">
			</body>
		</html>
	',
	// The preload link should be absent because the URL Metrics were collected before the script was printed at wp_body_open, causing the XPath to no longer be valid.
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
			</head>
			<body>
				<script>/* Something injected with wp_body_open */</script>
				<img data-od-unknown-tag src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800">
			</body>
		</html>
	',
);
