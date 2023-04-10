<?php
/**
 * Plugin Name: SQLite Database Integration
 * Module Name: SQLite Integration
 * Experimental: Yes
 * Description: SQLite database driver drop-in.
 * Author: WordPress Performance Team
 * Version: 2.0
 * Requires PHP: 5.6
 * Textdomain: sqlite-database-integration
 *
 * @package performance-lab
 */

define( 'SQLITE_MAIN_FILE', __FILE__ );

require_once __DIR__ . '/admin-page.php';
require_once __DIR__ . '/activate.php';
require_once __DIR__ . '/deactivate.php';
require_once __DIR__ . '/admin-notices.php';
require_once __DIR__ . '/health-check.php';
