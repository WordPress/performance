<?php
/**
 * Save some metrics used in the performance metrics tab.
 *
 * @since 1.0.0
 *
 * @return array|false An array of number and size of loaded assets, or false if the 'aea_enqueued_front_page_scripts'
 * or 'aea_enqueued_front_page_styles' transients are not set.
 */
function performance_lab_pm_get_loaded_assets() {
	if ( false === get_transient( 'aea_enqueued_front_page_scripts' ) || false === get_transient( 'aea_enqueued_front_page_styles' ) ) {
		return false;
	}

	$assets = array();

	$loaded_scripts = get_transient( 'aea_enqueued_front_page_scripts' );
	$loaded_styles  = get_transient( 'aea_enqueued_front_page_styles' );

	$assets['total_scripts_number'] = sizeof( $loaded_scripts );
	$assets['total_styles_number']  = sizeof( $loaded_styles );

	$total_scripts_size = 0;
	$total_styles_size  = 0;

	foreach ( $loaded_scripts as $script ) {
		$assets['total_scripts_size'] += $script['size'];
	}

	foreach ( $loaded_styles as $style ) {
		$assets['total_styles_size'] += $style['size'];
	}

	return $assets;
}
