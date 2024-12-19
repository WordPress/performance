<?php
return array(
	'set_up'   => static function ( Test_Image_Prioritizer_Helper $test_case ): void {
		add_filter(
			'od_breakpoint_max_widths',
			static function () {
				return array( 480, 600, 782 );
			}
		);

		$slug = od_get_url_metrics_slug( od_get_normalized_query_vars() );
		$sample_size = od_get_url_metrics_breakpoint_sample_size();

		$outside_viewport_rect = array_merge(
			$test_case->get_sample_dom_rect(),
			array(
				'top' => 100000,
			)
		);

		// Populate the mobile and desktop viewport groups only.
		foreach ( array( 400, 800 ) as $viewport_width ) {
			for ( $i = 0; $i < $sample_size; $i++ ) {
				OD_URL_Metrics_Post_Type::store_url_metric(
					$slug,
					$test_case->get_sample_url_metric(
						array(
							'viewport_width' => $viewport_width,
							'elements'       => array(
								array(
									'xpath'             => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::MAIN]/*[2][self::ARTICLE]/*[2][self::FIGURE]/*[1][self::IMG]',
									'isLCP'             => $viewport_width > 600,
									'intersectionRatio' => $viewport_width > 600 ? 1.0 : 0.1,
								),
								array(
									'xpath'              => '/*[1][self::HTML]/*[2][self::BODY]/*[2][self::MAIN]/*[4][self::DIV]',
									'isLCP'              => false,
									'intersectionRatio'  => 0.0,
									'intersectionRect'   => $outside_viewport_rect,
									'boundingClientRect' => $outside_viewport_rect,
								),
							),
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
				<h1>Example</h1>
				<main>
					<article id="post-2">
						<h2 class="entry-title">Last Post</h2>
						<div class="entry-content">
							<p>This post has no featured image!</p>
							<p>This paragraph adds vertical height.</p>
							<p>So does this one.</p>
							<p>And this one too.</p>
						</div>
					</article>
					<article id="post-1">
						<h2 class="entry-title">First Post</h2>
						<figure class="featured-media">
							<img src="https://example.com/featured-image.jpg" fetchpriority="high" width="1200" height="600" alt="Featured Image" class="attachment-post-thumbnail size-post-thumbnail wp-post-image" srcset="https://example.com/featured-image-1200.jpg 1200w, https://example.com/featured-image-600.jpg 600w, https://example.com/featured-image-300.jpg 300w" sizes="(max-width: 1200px) 100vw, 1200px">
						</figure>
						<div class="entry-content">
							<p>This post does have a featured image, and the server-side heuristics in WordPress cause it to get fetchpriority=high, but it should not have this since it is out of the viewport on mobile.</p>
						</div>
					</article>
					<p>Pretend this is a super long paragraph that pushes the next div out of the initial viewport.</p>
					<div style="background-image:url(https://example.com/foo-bg.jpg); width:100%; height: 200px;">This is so background!</div>
				</main>
			</body>
		</html>
	',
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
				<style>
					@media (scripting:enabled){.od-lazy-bg-image{background-image:none!important}}
				</style>
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/featured-image.jpg" imagesrcset="https://example.com/featured-image-1200.jpg 1200w, https://example.com/featured-image-600.jpg 600w, https://example.com/featured-image-300.jpg 300w" imagesizes="(max-width: 1200px) 100vw, 1200px" media="screen and (min-width: 783px)">
			</head>
			<body>
				<h1>Example</h1>
				<main>
					<article id="post-2">
						<h2 class="entry-title">Last Post</h2>
						<div class="entry-content">
							<p>This post has no featured image!</p>
							<p>This paragraph adds vertical height.</p>
							<p>So does this one.</p>
							<p>And this one too.</p>
						</div>
					</article>
					<article id="post-1">
						<h2 class="entry-title">First Post</h2>
						<figure class="featured-media">
							<img data-od-removed-fetchpriority="high" data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[2][self::MAIN]/*[2][self::ARTICLE]/*[2][self::FIGURE]/*[1][self::IMG]" src="https://example.com/featured-image.jpg"  width="1200" height="600" alt="Featured Image" class="attachment-post-thumbnail size-post-thumbnail wp-post-image" srcset="https://example.com/featured-image-1200.jpg 1200w, https://example.com/featured-image-600.jpg 600w, https://example.com/featured-image-300.jpg 300w" sizes="(max-width: 1200px) 100vw, 1200px">
						</figure>
						<div class="entry-content">
							<p>This post does have a featured image, and the server-side heuristics in WordPress cause it to get fetchpriority=high, but it should not have this since it is out of the viewport on mobile.</p>
						</div>
					</article>
					<p>Pretend this is a super long paragraph that pushes the next div out of the initial viewport.</p>
					<div class="od-lazy-bg-image" data-od-added-class data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[2][self::MAIN]/*[4][self::DIV]" style="background-image:url(https://example.com/foo-bg.jpg); width:100%; height: 200px;">This is so background!</div>
				</main>
			<script type="module">/* const lazyBgImageObserver ... */</script>
			<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
