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
	 * @param int $number_of_assets Number of assets to mock.
	 */
	public static function set_script_transient_with_data( int $number_of_assets = 5 ): void {
		$assets = array(
			'scripts' => array_fill(
				0,
				$number_of_assets,
				array(
					'src'  => 'script.js',
					'size' => 1000,
				)
			),
			'styles'  => array(),
		);
		set_transient( self::ASSETS_TRANSIENT, $assets );
	}

	/**
	 * Setting up the Styles transient.
	 *
	 * @param int $number_of_assets Number of assets to mock.
	 */
	public static function set_style_transient_with_data( int $number_of_assets = 5 ): void {
		$assets = array(
			'scripts' => array(),
			'styles'  => array_fill(
				0,
				$number_of_assets,
				array(
					'src'  => 'style.css',
					'size' => 1000,
				)
			),
		);
		set_transient( self::ASSETS_TRANSIENT, $assets );
	}

	/**
	 * Deleting the Assets transient.
	 */
	public static function set_asset_transient_with_no_data(): void {
		delete_transient( self::ASSETS_TRANSIENT );
	}
}
