<?php
return array(
	'set_up'   => static function (): void {
		ini_set( 'default_mimetype', 'text/html; charset=utf-8' ); // phpcs:ignore WordPress.PHP.IniSet.Risky
	},
	// Smallest PNG courtesy of <https://evanhahn.com/worlds-smallest-png/>.
	'buffer'   => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
			</head>
			<body>
				<div style="background-image:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQAAAAA3bvkkAAAACklEQVR4AWNgAAAAAgABc3UBGAAAAABJRU5ErkJggg==); width:100%; height: 200px;">This is so background!</div>
			</body>
		</html>
	',
	// There should be no data-od-xpath added to the DIV because it is using a data: URL for the background-image.
	'expected' => '
		<html lang="en">
			<head>
				<meta charset="utf-8">
				<title>...</title>
			</head>
			<body>
				<div style="background-image:url(data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQAAAAA3bvkkAAAACklEQVR4AWNgAAAAAgABc3UBGAAAAABJRU5ErkJggg==); width:100%; height: 200px;">This is so background!</div>
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
