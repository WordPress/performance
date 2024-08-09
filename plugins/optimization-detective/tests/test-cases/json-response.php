<?php
return array(
	'set_up'   => static function (): void {
		ini_set( 'default_mimetype', 'application/json' ); // phpcs:ignore WordPress.PHP.IniSet.Risky
	},
	'buffer'   => ' {"doc": "<html lang="en"><body><img src="https://www.example.com/logo.jpg" alt="Example Blog Logo" /></body></html>"}',
	'expected' => ' {"doc": "<html lang="en"><body><img src="https://www.example.com/logo.jpg" alt="Example Blog Logo" /></body></html>"}',
);
