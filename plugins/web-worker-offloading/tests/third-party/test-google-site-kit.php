<?php
/**
 * Test cases for google-site-kit.php in Web Worker Offloading.
 *
 * @package web-worker-offloading
 */
class Test_Google_Site_Kit extends WP_UnitTestCase {

	/**
	 * Test the function that configures WWO for Google Site Kit.
	 *
	 * @covers ::plwwo_google_site_kit_configure
	 */
	public function test_plwwo_google_site_kit_configure(): void {
		$configuration          = array();
		$expected_configuration = array(
			'globalFns'           => array( 'gtag', 'wp_has_consent' ),
			'forward'             => array( 'dataLayer.push', 'gtag' ),
			'mainWindowAccessors' => array(
				'_googlesitekitConsentCategoryMap',
				'_googlesitekitConsents',
				'wp_consent_type',
				'wp_fallback_consent_type',
				'wp_has_consent',
				'waitfor_consent_hook',
			),
		);

		$result = plwwo_google_site_kit_configure( $configuration );

		$this->assertEquals( $expected_configuration, $result );
	}

	/**
	 * Test the function that filters inline script attributes.
	 *
	 * @covers ::plwwo_google_site_kit_filter_inline_script_attributes
	 */
	public function test_plwwo_google_site_kit_filter_inline_script_attributes(): void {
		$attributes          = array( 'id' => 'google_gtagjs-js-consent-mode-data-layer' );
		$expected_attributes = array(
			'id'   => 'google_gtagjs-js-consent-mode-data-layer',
			'type' => 'text/partytown',
		);

		$result = plwwo_google_site_kit_filter_inline_script_attributes( $attributes );

		$this->assertEquals( $expected_attributes, $result );
	}
}
