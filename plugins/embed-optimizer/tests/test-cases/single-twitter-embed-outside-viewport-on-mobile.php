<?php
return array(
	'set_up'   => static function ( Test_Embed_Optimizer_Optimization_Detective $test_case ): void {
		foreach ( array_merge( od_get_breakpoint_max_widths(), array( 1000 ) ) as $i => $viewport_width ) {
			$elements = array(
				array(
					'xpath'                     => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::FIGURE]/*[1][self::DIV]',
					'isLCP'                     => true,
					'resizedBoundingClientRect' => array_merge( $test_case->get_sample_dom_rect(), array( 'height' => 500 + $i * 100 ) ),
				),
			);

			// Embed not visible on mobile.
			if ( 480 === $viewport_width ) {
				$elements[0]['intersectionRatio'] = 0;
				$elements[0]['isLCP']             = false;
			}

			$sample_size = od_get_url_metrics_breakpoint_sample_size();
			for ( $j = 0; $j < $sample_size; $j++ ) {
				OD_URL_Metrics_Post_Type::store_url_metric(
					od_get_url_metrics_slug( od_get_normalized_query_vars() ),
					$test_case->get_sample_url_metric(
						array(
							'viewport_width' => $viewport_width,
							'elements'       => $elements,
						)
					)
				);
			}
		}
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
				<style>
				@media (max-width: 480px) { #embed-optimizer-a7659db28ecaa36ddee6ae66857dabd8 { min-height: 500px; } }
				@media (min-width: 481px) and (max-width: 600px) { #embed-optimizer-a7659db28ecaa36ddee6ae66857dabd8 { min-height: 600px; } }
				@media (min-width: 601px) and (max-width: 782px) { #embed-optimizer-a7659db28ecaa36ddee6ae66857dabd8 { min-height: 700px; } }
				@media (min-width: 783px) { #embed-optimizer-a7659db28ecaa36ddee6ae66857dabd8 { min-height: 800px; } }
				</style>
				<link data-od-added-tag rel="preconnect" href="https://pbs.twimg.com" media="(min-width: 481px)">
				<link data-od-added-tag rel="preconnect" href="https://syndication.twitter.com" media="(min-width: 481px)">
			</head>
			<body>
				<figure data-od-added-id id="embed-optimizer-a7659db28ecaa36ddee6ae66857dabd8" class="wp-block-embed is-type-rich is-provider-twitter wp-block-embed-twitter">
					<div class="wp-block-embed__wrapper">
						<blockquote class="twitter-tweet" data-width="550" data-dnt="true"><p lang="en" dir="ltr">We want your feedback for the Privacy Sandbox 📨<br><br>Learn why your feedback is critical through real examples and learn how to provide it ↓ <a href="https://t.co/anGk6gWkbc">https://t.co/anGk6gWkbc</a></p>&mdash; Chrome for Developers (@ChromiumDev) <a href="https://twitter.com/ChromiumDev/status/1636796541368139777?ref_src=twsrc%5Etfw">March 17, 2023</a></blockquote>
						<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
					</div>
				</figure>
			</body>
		</html>
	',
);
