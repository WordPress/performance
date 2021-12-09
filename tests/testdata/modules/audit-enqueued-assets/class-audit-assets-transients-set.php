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

	const SCRIPT_TRANSIENT = 'aea_enqueued_scripts';
	const STYLES_TRANSIENT = 'aea_enqueued_styles';

	/**
	 * Script Transient example.
	 */
	const MOCK_SCRIPTS_TRANSIENT_CONTENT = array(
		'script1.js',
		'script3.js',
		'script3.js',
	);

	/**
	 * Style Transient example.
	 */
	const MOCK_STYLES_TRANSIENT_CONTENT = array(
		'style1.css',
		'style3.css',
		'style4.css',
	);

	/**
	 * Setting up the Script transient.
	 */
	public static function set_script_transient_with_data() {
		set_transient( self::SCRIPT_TRANSIENT, self::MOCK_SCRIPTS_TRANSIENT_CONTENT );
	}

	/**
	 * Deleting the Script transient.
	 */
	public static function set_script_transient_with_no_data() {
		delete_transient( self::SCRIPT_TRANSIENT );
	}

	/**
	 * Setting up the Styles transient.
	 */
	public static function set_style_transient_with_data() {
		set_transient( self::STYLES_TRANSIENT, self::MOCK_STYLES_TRANSIENT_CONTENT );
	}

	/**
	 * Deleting the Style transient.
	 */
	public static function set_style_transient_with_no_data() {
		delete_transient( self::STYLES_TRANSIENT );
	}
}

