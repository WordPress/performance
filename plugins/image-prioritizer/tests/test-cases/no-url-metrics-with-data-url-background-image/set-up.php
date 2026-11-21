<?php
return static function (): void {
	ini_set( 'default_mimetype', 'text/html; charset=utf-8' ); // phpcs:ignore WordPress.PHP.IniSet.Risky
	// Smallest PNG courtesy of <https://evanhahn.com/worlds-smallest-png/>.
	// There should be no data-od-xpath added to the DIV because it is using a data: URL for the background-image.
};
