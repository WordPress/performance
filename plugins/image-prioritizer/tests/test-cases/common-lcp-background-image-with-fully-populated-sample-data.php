<?php
return array(
	'set_up'   => static function ( Test_Image_Prioritizer_Helper $test_case ): void {
		$test_case->populate_url_metrics(
			array(
				array(
					'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::DIV]',
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
				<div style="background-image:url(https://example.com/foo-bg.jpg); width:100%; height: 200px;">This is so background!</div>
			</body>
		</html>
	',
	// Note: There should be no lazy-loading script or styles added here as the only background image is in the initial viewport.
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/foo-bg.jpg" media="screen">
			</head>
			<body>
				<div style="background-image:url(https://example.com/foo-bg.jpg); width:100%; height: 200px;">This is so background!</div>
			</body>
		</html>
	',
);
