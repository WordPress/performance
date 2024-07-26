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
				<img src="https://example.com/foo.jpg" alt="Foo" width="10" height="10" decoding="async">
				<img src="https://example.com/bar.jpg" alt="Bar" width="1200" height="800" decoding="async" fetchpriority="high">
				<img src="https://example.com/baz.jpg" alt="Baz" width="10" height="10" decoding="async">
				<img src="https://example.com/qux.jpg" alt="Qux" width="1000" height="1000" decoding="async" loading="lazy">
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
				<img data-od-unknown-tag data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::IMG]" src="https://example.com/foo.jpg" alt="Foo" width="10" height="10" decoding="async">
				<img data-od-unknown-tag data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[2][self::IMG]" src="https://example.com/bar.jpg" alt="Bar" width="1200" height="800" decoding="async" fetchpriority="high">
				<img data-od-unknown-tag data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[3][self::IMG]" src="https://example.com/baz.jpg" alt="Baz" width="10" height="10" decoding="async">
				<img data-od-unknown-tag data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[4][self::IMG]" src="https://example.com/qux.jpg" alt="Qux" width="1000" height="1000" decoding="async" loading="lazy">
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
