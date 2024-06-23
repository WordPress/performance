<?php
/**
 * This script runs before the test command is executed.
 *
 * @package performance
 */

// On local environment.
if ( ! getenv( 'WORDPRESS_DB_USER' ) ) {
	echo "Please use 'npm run test-php' instead of running the composer test command directly." . PHP_EOL;
	exit( 1 );
}
