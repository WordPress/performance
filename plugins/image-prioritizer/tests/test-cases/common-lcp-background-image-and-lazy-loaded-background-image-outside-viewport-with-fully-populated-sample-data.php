<?php
return array(
	'set_up'   => static function ( Test_Image_Prioritizer_Helper $test_case ): void {
		$outside_viewport_rect = array_merge(
			$test_case->get_sample_dom_rect(),
			array(
				'top' => 100000,
			)
		);

		$test_case->populate_url_metrics(
			array(
				array(
					'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::DIV]',
					'isLCP' => true,
				),
				array(
					'xpath'              => '/*[1][self::HTML]/*[2][self::BODY]/*[3][self::DIV]',
					'isLCP'              => false,
					'intersectionRatio'  => 0.0,
					'intersectionRect'   => $outside_viewport_rect,
					'boundingClientRect' => $outside_viewport_rect,
				),
				array(
					'xpath'              => '/*[1][self::HTML]/*[2][self::BODY]/*[4][self::DIV]',
					'isLCP'              => false,
					'intersectionRatio'  => 0.0,
					'intersectionRect'   => $outside_viewport_rect,
					'boundingClientRect' => $outside_viewport_rect,
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
				<p>Pretend this is a super long paragraph that pushes the next div out of the initial viewport.</p>
				<div style="background-image:url(https://example.com/bar-bg.jpg); width:100%; height: 200px;">This is so background!</div>
				<div style="background-image:url(https://example.com/baz-bg.jpg); width:100%; height: 200px;">This is so background!</div>
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
				<link data-od-added-tag rel="preload" fetchpriority="high" as="image" href="https://example.com/foo-bg.jpg" media="screen">
			</head>
			<body>
				<div style="background-image:url(https://example.com/foo-bg.jpg); width:100%; height: 200px;">This is so background!</div>
				<p>Pretend this is a super long paragraph that pushes the next div out of the initial viewport.</p>
				<div class="od-lazy-bg-image" data-od-added-class style="background-image:url(https://example.com/bar-bg.jpg); width:100%; height: 200px;">This is so background!</div>
				<div class="od-lazy-bg-image" data-od-added-class style="background-image:url(https://example.com/baz-bg.jpg); width:100%; height: 200px;">This is so background!</div>
				<script type="module">/* const lazyBgImageObserver ... */</script>
			</body>
		</html>
	',
);
