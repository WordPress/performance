<?php
/**
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Class Audit_Assets_Mock_Assets mocks assets for testing.
 *
 * @since n.e.x.t
 */
class Audit_Assets_Mock_Assets {

	/**
	 * Setting up the Script transient.
	 *
	 * @param 'scripts'|'styles'   $type             Type.
	 * @param int                  $number_of_assets Number of assets to mock.
	 * @param int                  $error_count      Error count to mock.
	 * @param array<string, mixed> $assets           Array of assets to mock.
	 * @return array<string, mixed> Array of assets with mocked data.
	 */
	public static function mock_assets( string $type, int $number_of_assets = 5, int $error_count = 0, array $assets = array() ): array {
		$assets[ $type ] = array();
		for ( $i = 0; $i < $number_of_assets; $i++ ) {
			$error = null;
			if ( $error_count > 0 ) {
				--$error_count;
				$error = new WP_Error( '404', 'Not found' );
			}

			$assets[ $type ][] = array(
				'src'   => 'scripts' === $type ? 'script.js' : 'style.css',
				'size'  => $error instanceof WP_Error ? null : 1000,
				'error' => $error,
			);
		}
		return $assets;
	}
}
