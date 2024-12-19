<?php
return array(
	'set_up'   => static function (): void {},
	'buffer'   => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
			</head>
			<body>
				<img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy">
				<p>Pretend this is a super long paragraph that pushes the next div out of the initial viewport.</p>
				<div style="background-image:url(https://example.com/foo-bg.jpg); width:100%; height: 200px;">This is so background!</div>
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
				<img data-od-unknown-tag data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" loading="lazy">
				<p>Pretend this is a super long paragraph that pushes the next div out of the initial viewport.</p>
				<div data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[3][self::DIV]" style="background-image:url(https://example.com/foo-bg.jpg); width:100%; height: 200px;">This is so background!</div>
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
