<?php
/**
 * Run linting and formatting on plugins PHP files.
 *
 * Example usage:
 *
 * $ WPP_PLUGIN=plugin1,plugin2 php bin/phpcs/lint.php
 * Would run phpcs on plugin1 and plugin2.
 *
 * $ WPP_PLUGIN=plugin1,plugin2 WPP_FIX=1 php bin/phpcs/lint.php
 * Would run phpcbf on plugin1 and plugin2.
 *
 * $ php bin/phpcs/lint.php
 * Would run phpcs on all plugins.
 *
 * $ WPP_FIX=1 php bin/phpcs/lint.php
 * Would run phpcbf on all plugins.
 *
 * @codeCoverageIgnore
 *
 * @package performance-lab
 */

// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.system_calls_passthru
// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

if ( 'cli' !== php_sapi_name() ) {
	echo 'This script can only be run from the command line.';
	exit;
}

$args            = array_slice( $argv, 1 );
$plugin_root     = dirname( dirname( __DIR__ ) );
$plugins         = json_decode( file_get_contents( $plugin_root . '/plugins.json' ), true )['plugins'];
$plugins_path    = $plugin_root . '/plugins';
$vendor_bin      = $plugin_root . '/build-cs/vendor/bin';
$fix             = getenv( 'WPP_FIX' );
$plugins_to_lint = getenv( 'WPP_PLUGIN' );
$exit_code       = 0;

if ( empty( $plugins_to_lint ) ) {
	$plugins_to_lint = $plugins;
} else {
	$plugins_to_lint = explode( ',', $plugins_to_lint );
}

foreach ( $plugins_to_lint as $plugin ) {
	if ( ! in_array( $plugin, $plugins, true ) ) {
		logger( "Plugin $plugin not found in plugins.json\n", 'w' );
		continue;
	}

	$plugin_path = $plugins_path . '/' . $plugin;

	if ( ! is_dir( $plugin_path ) ) {
		logger( "Plugin $plugin not found in plugins directory\n", 'w' );
		continue;
	}

	$is_dir_changed = chdir( $plugin_path );

	if ( ! $is_dir_changed ) {
		logger( "Could not change to $plugin_path\n", 'e' );
		continue;
	}

	$cmd = sprintf( '%s/%s', $vendor_bin, $fix ? 'phpcbf' : 'phpcs' );

	if ( ! file_exists( $cmd ) ) {
		logger( "Could not find phpcs in $vendor_bin\n. Run composer install first.", 'e' );
		exit( 1 );
	}

	logger( $fix ? "> Formatting $plugin\n" : "> Linting $plugin\n", 'i' );

	passthru( $cmd . ' ' . implode( ' ', $args ), $result_code );

	if ( 0 !== $result_code ) {
		$exit_code = $result_code;
	}
}

exit( $exit_code );

/**
 * Log messages with colors.
 *
 * @param string $str Message to log.
 * @param string $type Type of message.
 *
 * @return void
 */
function logger( $str, $type = 'i' ) {
	$str = match ( $type ) {
		'e' => "\033[31m$str \033[0m\n",
		's' => "\033[32m$str \033[0m\n",
		'w' => "\033[33m$str \033[0m\n",
		'i' => "\033[36m$str \033[0m\n",
		default => $str,
	};

	echo $str;
}
