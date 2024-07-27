<?php
return array(
	'set_up'   => static function (): void {
		ini_set( 'default_mimetype', 'application/rss+xml' ); // phpcs:ignore WordPress.PHP.IniSet.Risky
	},
	'buffer'   => '<?xml version="1.0" encoding="UTF-8"?>
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
	'expected' => '<?xml version="1.0" encoding="UTF-8"?>
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
