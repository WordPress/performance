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
				<img id="no-src" alt="">
				<img id="empty-src" src="" alt="">
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
				<img id="no-src" alt="">
				<img id="empty-src" src="" alt="">
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
