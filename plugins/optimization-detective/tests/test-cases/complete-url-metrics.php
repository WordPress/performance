<?php
return array(
	'set_up'   => static function ( Test_OD_Optimization $test_case ): void {
		ini_set( 'default_mimetype', 'text/html; charset=utf-8' ); // phpcs:ignore WordPress.PHP.IniSet.Risky

		// Normalize the data for computing the current URL Metrics ETag to work around the issue where there is no
		// global variable storing the OD_Tag_Visitor_Registry instance along with any registered tag visitors, so
		// during set up we do not know what the ETag will look like. The current ETag is only established when
		// the output begins to be processed by od_optimize_template_output_buffer().
		add_filter( 'od_current_url_metrics_etag_data', '__return_empty_array' );

		$test_case->populate_url_metrics(
			array(
				array(
					'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]',
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
				<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy">
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
				<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy">
			</body>
		</html>
	',
);
