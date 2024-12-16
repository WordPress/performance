<?php
return array(
	'set_up'   => static function (): void {},

	/*
	 * Example 1 comes from Avada's Fusion_Images lazy images which replaces the srcset attribute with data-srcset but
	 * which leaves the src attribute as-is.
	 *
	 * Example 2 comes from Speed Optimizer v7.7.2 by Site Ground which uses lazysizes v5.3.1.
	 * See <https://plugins.trac.wordpress.org/browser/sg-cachepress/tags/7.7.2/core/Lazy_Load/Lazy_Load_Images.php>.
	 */
	'buffer'   => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
				<script>/* custom lazy-loading */</script>
			</head>
			<body>
				<!-- Example 1 -->
				<img
					src="https://example.com/foo.webp"
					data-orig-src="https://example.com/foo.webp"
					width="1000"
					height="800"
					class="lazyload wp-image-1"
					srcset="data:image/svg+xml,%3Csvg%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20width%3D%271000%27%20height%3D%27800%27%20viewBox%3D%270%200%201000%20800%27%3E%3Crect%20width%3D%271000%27%20height%3D%27800%27%20fill-opacity%3D%220%22%2F%3E%3C%2Fsvg%3E"
					data-srcset="https://example.com/foo-200x91.webp 200w, https://example.com/foo-300x136.webp 300w, https://example.com/foo-400x181.webp 400w, https://example.com/foo-600x272.webp 600w, https://example.com/foo-768x348.webp 768w, https://example.com/foo-800x362.webp 800w, https://example.com/foo-1024x463.webp 1024w, https://example.com/foo-1200x543.webp 1200w, https://example.com/foo-1536x695.webp 1536w, https://example.com/foo.webp 1920w"
					data-sizes="auto"
					data-orig-sizes="(max-width: 767px) 100vw, 1920px"
				>

				<!-- Example 1 extended to PICTURE, where none of these should be tracked in URL Metrics -->
				<picture>
					<source type="image/avif" srcset="https://example.com/foo-300x225.avif 300w, https://example.com/foo-1024x768.avif 1024w, https://example.com/foo-768x576.avif 768w, https://example.com/foo-1536x1152.avif 1536w, https://example.com/foo-2048x1536.avif 2048w" sizes="(max-width: 600px) 480px, 800px">
					<img class="lazyload" width="1200" height="800" src="https://example.com/foo.avif" alt="Foo" srcset="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="(max-width: 600px) 480px, 800px">
				</picture>
				<picture>
					<source type="image/avif" srcset="https://example.com/foo-300x225.avif 300w, https://example.com/foo-1024x768.avif 1024w, https://example.com/foo-768x576.avif 768w, https://example.com/foo-1536x1152.avif 1536w, https://example.com/foo-2048x1536.avif 2048w" sizes="(max-width: 600px) 480px, 800px">
					<img class="lazyload" width="1200" height="800" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" alt="Foo">
				</picture>
				<picture>
					<source type="image/avif" srcset="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="(max-width: 600px) 480px, 800px">
					<img class="lazyload" width="1200" height="800" src="https://example.com/foo.avif" alt="Foo">
				</picture>

				<!-- Example 2 -->
				<img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-src="https://example.com/bar.jpg" data-srcset="https://example.com/bar-large.jpg 1000w, https://example.com/bar-large.jpg 1000w" sizes="(max-width: 556px) 100vw, 556px" alt="Bar" class="attachment-large size-large wp-image-2 has-transparency lazyload" width="500" height="300">
				<noscript>
					<img src="https://example.com/bar.jpg" srcset="https://example.com/bar-large.jpg 1000w, https://example.com/bar-large.jpg 1000w" sizes="(max-width: 556px) 100vw, 556px" alt="Bar" class="attachment-large size-large wp-image-2 has-transparency lazyload" width="500" height="300">
				</noscript>
			</body>
		</html>
	',
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
				<script>/* custom lazy-loading */</script>
			</head>
			<body>
				<!-- Example 1 -->
				<img
					src="https://example.com/foo.webp"
					data-orig-src="https://example.com/foo.webp"
					width="1000"
					height="800"
					class="lazyload wp-image-1"
					srcset="data:image/svg+xml,%3Csvg%20xmlns%3D%27http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%27%20width%3D%271000%27%20height%3D%27800%27%20viewBox%3D%270%200%201000%20800%27%3E%3Crect%20width%3D%271000%27%20height%3D%27800%27%20fill-opacity%3D%220%22%2F%3E%3C%2Fsvg%3E"
					data-srcset="https://example.com/foo-200x91.webp 200w, https://example.com/foo-300x136.webp 300w, https://example.com/foo-400x181.webp 400w, https://example.com/foo-600x272.webp 600w, https://example.com/foo-768x348.webp 768w, https://example.com/foo-800x362.webp 800w, https://example.com/foo-1024x463.webp 1024w, https://example.com/foo-1200x543.webp 1200w, https://example.com/foo-1536x695.webp 1536w, https://example.com/foo.webp 1920w"
					data-sizes="auto"
					data-orig-sizes="(max-width: 767px) 100vw, 1920px"
				>

				<!-- Example 1 extended to PICTURE, where none of these should be tracked in URL Metrics -->
				<picture>
					<source type="image/avif" srcset="https://example.com/foo-300x225.avif 300w, https://example.com/foo-1024x768.avif 1024w, https://example.com/foo-768x576.avif 768w, https://example.com/foo-1536x1152.avif 1536w, https://example.com/foo-2048x1536.avif 2048w" sizes="(max-width: 600px) 480px, 800px">
					<img class="lazyload" width="1200" height="800" src="https://example.com/foo.avif" alt="Foo" srcset="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="(max-width: 600px) 480px, 800px">
				</picture>
				<picture>
					<source type="image/avif" srcset="https://example.com/foo-300x225.avif 300w, https://example.com/foo-1024x768.avif 1024w, https://example.com/foo-768x576.avif 768w, https://example.com/foo-1536x1152.avif 1536w, https://example.com/foo-2048x1536.avif 2048w" sizes="(max-width: 600px) 480px, 800px">
					<img class="lazyload" width="1200" height="800" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" alt="Foo">
				</picture>
				<picture>
					<source type="image/avif" srcset="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-srcset="https://example.com/foo-300x225.jpg 300w, https://example.com/foo-1024x768.jpg 1024w, https://example.com/foo-768x576.jpg 768w, https://example.com/foo-1536x1152.jpg 1536w, https://example.com/foo-2048x1536.jpg 2048w" sizes="(max-width: 600px) 480px, 800px">
					<img class="lazyload" width="1200" height="800" src="https://example.com/foo.avif" alt="Foo">
				</picture>

				<!-- Example 2 -->
				<img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" data-src="https://example.com/bar.jpg" data-srcset="https://example.com/bar-large.jpg 1000w, https://example.com/bar-large.jpg 1000w" sizes="(max-width: 556px) 100vw, 556px" alt="Bar" class="attachment-large size-large wp-image-2 has-transparency lazyload" width="500" height="300">
				<noscript>
					<img src="https://example.com/bar.jpg" srcset="https://example.com/bar-large.jpg 1000w, https://example.com/bar-large.jpg 1000w" sizes="(max-width: 556px) 100vw, 556px" alt="Bar" class="attachment-large size-large wp-image-2 has-transparency lazyload" width="500" height="300">
				</noscript>
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
