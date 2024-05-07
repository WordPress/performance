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

	const SCRIPT_TRANSIENT = 'aea_enqueued_front_page_scripts';
	const STYLES_TRANSIENT = 'aea_enqueued_front_page_styles';

	/**
	 * Setting up the Script transient.
	 *
	 * @param int $number_of_assets Number of assets to mock.
	 */
	public static function set_script_transient_with_data( $number_of_assets = 5 ): void {
		$scripts = array_fill(
			0,
			$number_of_assets,
			array(
				'src'  => 'script.js',
				'size' => 1000,
			)
		);
		set_transient( self::SCRIPT_TRANSIENT, $scripts );
	}

	/**
	 * Deleting the Script transient.
	 */
	public static function set_script_transient_with_no_data(): void {
		delete_transient( self::SCRIPT_TRANSIENT );
	}

	/**
	 * Setting up the Styles transient.
	 *
	 * @param int $number_of_assets Number of assets to mock.
	 */
	public static function set_style_transient_with_data( $number_of_assets = 5 ): void {
		$styles = array_fill(
			0,
			$number_of_assets,
			array(
				'src'  => 'style.css',
				'size' => 1000,
			)
		);
		set_transient( self::STYLES_TRANSIENT, $styles );
	}

	/**
	 * Deleting the Style transient.
	 */
	public static function set_style_transient_with_no_data(): void {
		delete_transient( self::STYLES_TRANSIENT );
	}
}
