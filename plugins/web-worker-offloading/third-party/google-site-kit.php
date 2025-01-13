<?php
/**
 * Web Worker Offloading integration with Site Kit by Google.
 *
 * @since 0.2.0
 * @package web-worker-offloading
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Configures WWO for Site Kit and Google Analytics.
 *
 * @since 0.2.0
 * @access private
 * @link https://partytown.builder.io/google-tag-manager#forward-events
 *
 * @param array<string, mixed>|mixed $configuration Configuration.
 * @return array<string, mixed> Configuration.
 */
function plwwo_google_site_kit_configure( $configuration ): array {
	$configuration = (array) $configuration;

	$configuration['globalFns'][] = 'gtag'; // Allow calling from other Partytown scripts.
	$configuration['globalFns'][] = 'wp_has_consent'; // Allow calling function from main thread. See <https://github.com/google/site-kit-wp/blob/abbb74ff21f98a8779fbab0eeb9a16279a122bc4/assets/js/consent-mode/consent-mode.js#L61C13-L61C27>.

	// Expose on the main tread. See <https://partytown.builder.io/forwarding-event>.
	$configuration['forward'][] = 'dataLayer.push';
	$configuration['forward'][] = 'gtag';

	// See <https://github.com/google/site-kit-wp/blob/abbb74ff21f98a8779fbab0eeb9a16279a122bc4/includes/Core/Consent_Mode/Consent_Mode.php#L244-L259>,
	// and <https://github.com/google/site-kit-wp/blob/abbb74ff21f98a8779fbab0eeb9a16279a122bc4/assets/js/consent-mode/consent-mode.js>.
	$configuration['mainWindowAccessors'][] = '_googlesitekitConsentCategoryMap';
	$configuration['mainWindowAccessors'][] = '_googlesitekitConsents';
	$configuration['mainWindowAccessors'][] = 'wp_consent_type';
	$configuration['mainWindowAccessors'][] = 'wp_fallback_consent_type';
	$configuration['mainWindowAccessors'][] = 'wp_has_consent';
	$configuration['mainWindowAccessors'][] = 'waitfor_consent_hook';

	return $configuration;
}
add_filter( 'plwwo_configuration', 'plwwo_google_site_kit_configure' );

plwwo_mark_scripts_for_offloading(
	array(
		'google_gtagjs',
		'googlesitekit-consent-mode',
	)
);

/**
 * Filters inline script attributes to offload Google Site Kit's GTag script tag to Partytown.
 *
 * @since 0.2.0
 * @access private
 * @link https://github.com/google/site-kit-wp/blob/abbb74ff21f98a8779fbab0eeb9a16279a122bc4/includes/Core/Consent_Mode/Consent_Mode.php#L244-L259
 *
 * @param array|mixed $attributes Script attributes.
 * @return array|mixed Filtered inline script attributes.
 */
function plwwo_google_site_kit_filter_inline_script_attributes( $attributes ) {
	if ( isset( $attributes['id'] ) && 'google_gtagjs-js-consent-mode-data-layer' === $attributes['id'] ) {
		wp_enqueue_script( 'web-worker-offloading' );
		$attributes['type'] = 'text/partytown';
	}
	return $attributes;
}

add_filter( 'wp_inline_script_attributes', 'plwwo_google_site_kit_filter_inline_script_attributes' );
