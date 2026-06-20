<?php
return static function (): void {
	/*
	 * This is intentionally not 'application/json'. This is to test whether od_optimize_template_output_buffer()
	 * is checking whether the output starts with '<' (after whitespace is trimmed).
	 */
	ini_set( 'default_mimetype', 'text/html' ); // phpcs:ignore WordPress.PHP.IniSet.Risky
};
