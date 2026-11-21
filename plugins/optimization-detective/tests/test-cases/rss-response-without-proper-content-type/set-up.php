<?php
return static function (): void {
	// This is intentionally not application/rss+xml as it is testing whether the first tag is HTML.
	ini_set( 'default_mimetype', 'text/html' ); // phpcs:ignore WordPress.PHP.IniSet.Risky
	// Also omitting the XML processing instruction.
};
