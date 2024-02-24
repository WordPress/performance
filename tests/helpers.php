<?php

final class WPP_Tests_Helpers {

	/**
	 * This function is documented at <https://github.com/Yoast/wp-test-utils/blob/46566884fe74d2f758273efd71f6c07c7d2e4f53/src/WPIntegration/bootstrap-functions.php>.
	 *
	 * @return string|false The path to the WP test directory, or false if it could not be determined.
	 */
	public static function get_path_to_wp_test_dir() {
		// phpcs:disable WordPress.PHP.YodaConditions.NotYoda
		// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
		/**
		 * Normalizes all slashes in a file path to forward slashes.
		 *
		 * @param string $path File path.
		 *
		 * @return string The file path with normalized slashes.
		 */
		$normalize_path = static function ( $path ) {
			return \str_replace( '\\', '/', $path );
		};

		if ( \getenv( 'WP_TESTS_DIR' ) !== false ) {
			$tests_dir = \getenv( 'WP_TESTS_DIR' );
			$tests_dir = \realpath( $tests_dir );
			if ( $tests_dir !== false ) {
				$tests_dir = $normalize_path( $tests_dir ) . '/';
				if ( \is_dir( $tests_dir ) === true
					&& @\file_exists( $tests_dir . 'includes/bootstrap.php' )
				) {
					return $tests_dir;
				}
			}

			unset( $tests_dir );
		}

		if ( \getenv( 'WP_DEVELOP_DIR' ) !== false ) {
			$dev_dir = \getenv( 'WP_DEVELOP_DIR' );
			$dev_dir = \realpath( $dev_dir );
			if ( $dev_dir !== false ) {
				$dev_dir = $normalize_path( $dev_dir ) . '/';
				if ( \is_dir( $dev_dir ) === true
					&& @\file_exists( $dev_dir . 'tests/phpunit/includes/bootstrap.php' )
				) {
					return $dev_dir . 'tests/phpunit/';
				}
			}

			unset( $dev_dir );
		}

		/*
		* If neither of the constants was set, check whether the plugin is installed
		* in `src/wp-content/plugins`. In that case, this file would be in
		* `src/wp-content/plugins/plugin-name/vendor/yoast/wp-test-utils/src/WPIntegration`.
		*/
		if ( @\file_exists( __DIR__ . '/../../../../../../../../../tests/phpunit/includes/bootstrap.php' ) ) {
			$tests_dir = __DIR__ . '/../../../../../../../../../tests/phpunit/';
			$tests_dir = \realpath( $tests_dir );
			if ( $tests_dir !== false ) {
				return $normalize_path( $tests_dir ) . '/';
			}

			unset( $tests_dir );
		}

		/*
		* Last resort: see if this is a typical WP-CLI scaffold command set-up where a subset of
		* the WP test files have been put in the system temp directory.
		*/
		$tests_dir = \sys_get_temp_dir() . '/wordpress-tests-lib';
		$tests_dir = \realpath( $tests_dir );
		if ( $tests_dir !== false ) {
			$tests_dir = $normalize_path( $tests_dir ) . '/';
			if ( \is_dir( $tests_dir ) === true
				&& @\file_exists( $tests_dir . 'includes/bootstrap.php' )
			) {
				return $tests_dir;
			}
		}

		return false;
		// phpcs:enable WordPress.PHP.YodaConditions.NotYoda
		// phpcs:enable WordPress.PHP.NoSilencedErrors.Discouraged
	}
}
