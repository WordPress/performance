<?php
return array(
	'set_up'   => static function (): void {
		// This is intentionally not application/rss+xml as it is testing whether the first tag is HTML.
		ini_set( 'default_mimetype', 'text/html' ); // phpcs:ignore WordPress.PHP.IniSet.Risky
	},
	// Also omitting the XML processing instruction.
	'buffer'   => '
		<rss version="2.0">
			<channel>
				<title>Example Blog</title>
				<link>https://www.example.com</link>
				<description>
					<img src="https://www.example.com/logo.jpg" alt="Example Blog Logo" />
					A blog about technology, design, and culture.
				</description>
				<language>en-us</language>
			</channel>
		</rss>
	',
	'expected' => '
		<rss version="2.0">
			<channel>
				<title>Example Blog</title>
				<link>https://www.example.com</link>
				<description>
					<img src="https://www.example.com/logo.jpg" alt="Example Blog Logo" />
					A blog about technology, design, and culture.
				</description>
				<language>en-us</language>
			</channel>
		</rss>
	',
);
