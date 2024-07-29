<?php
return array(
	'set_up'   => static function ( Test_Image_Prioritizer_Helper $test_case ): void {
		$test_case->populate_url_metrics(
			array(
				array(
					'isLCP' => true,
					'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
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
				<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" fetchpriority="high">
			</body>
		</html>
	',
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/foo.jpg" media="screen">
			</head>
			<body>
				<img data-od-fetchpriority-already-added src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" fetchpriority="high">
			</body>
		</html>
	',
);
