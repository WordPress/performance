<?php
return array(
	'set_up'   => static function (): void {},
	// Smallest PNG courtesy of <https://evanhahn.com/worlds-smallest-png/>.
	'buffer'   => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
			</head>
			<body>
				<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQAAAAA3bvkkAAAACklEQVR4AWNgAAAAAgABc3UBGAAAAABJRU5ErkJggg==" alt="">
			</body>
		</html>
	',
	// There should be no data-od-xpath added to the IMG because it is using a data: URL.
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
			</head>
			<body>
				<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQAAAAA3bvkkAAAACklEQVR4AWNgAAAAAgABc3UBGAAAAABJRU5ErkJggg==" alt="">
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
