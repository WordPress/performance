<?php
return array(
	'set_up'   => static function (): void {
		ini_set( 'default_mimetype', 'application/xhtml+xml; charset=utf-8' ); // phpcs:ignore WordPress.PHP.IniSet.Risky
	},
	'buffer'   => '<?xml version="1.0" encoding="UTF-8"?>
		<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
		<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
			<head>
			  <meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8" />
			  <title>XHTML 1.0 Strict Example</title>
			</head>
			<body>
				<p><img src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" /></p>
			</body>
		</html>
	',
	'expected' => '<?xml version="1.0" encoding="UTF-8"?>
		<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
		<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
			<head>
			  <meta http-equiv="Content-Type" content="application/xhtml+xml; charset=utf-8" />
			  <title>XHTML 1.0 Strict Example</title>
			</head>
			<body>
				<p><img data-od-xpath="/*[1][self::HTML]/*[2][self::BODY]/*[1][self::P]/*[1][self::IMG]" src="https://example.com/foo.jpg" alt="Foo" width="1200" height="800" /></p>
				<script type="module">/* import detect ... */</script>
			</body>
		</html>
	',
);
