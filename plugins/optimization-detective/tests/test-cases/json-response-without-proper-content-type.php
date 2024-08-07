<?php
return array(
	'set_up'   => static function (): void {
		/*
		 * This is intentionally not 'application/json'. This is to test whether od_optimize_template_output_buffer()
		 * is checking whether the output starts with '<' (after whitespace is trimmed).
		 */
		ini_set( 'default_mimetype', 'text/html' ); // phpcs:ignore WordPress.PHP.IniSet.Risky
	},
	'buffer'   => ' {"doc": "<html lang="en"><body><img src="https://www.example.com/logo.jpg" alt="Example Blog Logo" /></body></html>"}',
	'expected' => ' {"doc": "<html lang="en"><body><img src="https://www.example.com/logo.jpg" alt="Example Blog Logo" /></body></html>"}',
);
