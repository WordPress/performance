<?php
/**
 * @package performance-lab
 * @since 1.0.0
 */

/**
 * Class Audit_Assets_Transients_Set sets and deletes audit-enqueued-assets transients with mock data.
 *
 * @since 1.0.0
 */
class Audit_Assets_Transients_Set {

	const ASSETS_TRANSIENT = 'aea_blocking_assets';

	/**
	 * Setting up the Script transient.
	 *
	 * @param 'scripts'|'styles' $type             Type.
	 * @param int                $number_of_assets Number of assets to mock.
	 * @param int                $error_count      Error count to mock.
	 */
	public static function set_assets_transient_with_data( string $type, int $number_of_assets = 5, int $error_count = 0 ): void {
		$assets = get_transient( self::ASSETS_TRANSIENT );
		if ( ! is_array( $assets ) ) {
			$assets = array();
		}
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
		set_transient( self::ASSETS_TRANSIENT, $assets );
	}
}
