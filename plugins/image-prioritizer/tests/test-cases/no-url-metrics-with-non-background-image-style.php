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
				<div style="background-color: black; color: white; width:100%; height: 200px;">This is so background!</div>
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
				<div style="background-color: black; color: white; width:100%; height: 200px;">This is so background!</div>
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
