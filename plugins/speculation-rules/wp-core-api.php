<?php
/**
 * Support for WordPress Core API for Speculative Loading.
 *
 * @package speculation-rules
 * @since 1.5.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Filters the WordPress Core speculation rules configuration to use the settings configured in the plugin.
 *
 * By default, this will override the Core implementation to use moderate prerender instead of conservative prefetch.
 *
 * @since 1.5.0
 *
 * @param array<string, string>|null|mixed $config Associative array with 'mode' and 'eagerness' keys, or `null`.
 * @return array<string, string>|null Filtered $config.
 */
function plsr_filter_speculation_rules_configuration( $config ): ?array {
	/*
	 * If speculative loading should be disable per the WordPress Core configuration, respect that value, unless pretty
	 * permalinks are disabled and the plugin-specific filter to opt in to the feature despite that is set to true.
	 * This is present for backward compatibility so that usage of the plugin-specific filter does not break.
	 */
	if (
		null === $config &&
		/** This filter is documented in plugin-api.php */
		( (bool) get_option( 'permalink_structure' ) || ! (bool) apply_filters( 'plsr_enabled_without_pretty_permalinks', false ) )
	) {
		return null;
	}

	$option = plsr_get_stored_setting_value();
	return array(
		'mode'      => $option['mode'],
		'eagerness' => $option['eagerness'],
	);
}

/**
 * Filters the WordPress Core speculation rules paths for which speculative loading should be disabled.
 *
 * This is present for backward compatibility so that usage of the plugin-specific filter does not break.
 *
 * @since 1.5.0
 *
 * @param string[]|mixed $href_exclude_paths Additional path patterns to disable speculative loading for.
 * @param string         $mode               Mode used to apply speculative loading. Either 'prefetch' or 'prerender'.
 * @return string[] Filtered $href_exclude_paths.
 */
function plsr_filter_speculation_rules_exclude_paths( $href_exclude_paths, string $mode ): array {
	if ( ! is_array( $href_exclude_paths ) ) {
		$href_exclude_paths = (bool) $href_exclude_paths ? (array) $href_exclude_paths : array();
	}

	/** This filter is documented in plugin-api.php */
	return (array) apply_filters( 'plsr_speculation_rules_href_exclude_paths', $href_exclude_paths, $mode );
}
