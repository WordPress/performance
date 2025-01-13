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
				<noscript>
					<img src="https://example.com/pixel.gif" alt="" width="1" height="1">
				</noscript>
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
				<noscript>
					<img src="https://example.com/pixel.gif" alt="" width="1" height="1">
				</noscript>
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
