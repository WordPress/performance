<?php
return array(
	'set_up'   => static function ( Test_OD_Optimization $test_case ): void {
		$slug = od_get_url_metrics_slug( od_get_normalized_query_vars() );
		foreach ( array_merge( od_get_breakpoint_max_widths(), array( 1000 ) ) as $viewport_width ) {

			$elements = array();
			for ( $i = 1; $i < WP_HTML_Tag_Processor::MAX_SEEK_OPS; $i++ ) {
				$elements[] = array(
					'xpath' => sprintf( '/*[1][self::HTML]/*[2][self::BODY]/*[%d][self::IMG]', $i ),
					'isLCP' => false,
				);
			}

			OD_URL_Metrics_Post_Type::store_url_metric(
				$slug,
				$test_case->get_validated_url_metric(
					$viewport_width,
					$elements
				)
			);
		}
	},
	'buffer'   => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
			</head>
			<body>
				' .
				join(
					"\n",
					call_user_func(
						static function () {
							$tags = array();
							for ( $i = 1; $i < WP_HTML_Tag_Processor::MAX_SEEK_OPS + 1; $i++ ) {
								$tags[] = sprintf( '<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy">' );
							}
							return $tags;
						}
					)
				) .
				'
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
				' .
				join(
					"\n",
					call_user_func(
						static function () {
							$tags = array();
							for ( $i = 1; $i < WP_HTML_Tag_Processor::MAX_SEEK_OPS + 1; $i++ ) {
								$tags[] = sprintf( '<img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[%d][self::IMG]" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy">', $i );
							}
							return $tags;
						}
					)
				) .
				'
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
