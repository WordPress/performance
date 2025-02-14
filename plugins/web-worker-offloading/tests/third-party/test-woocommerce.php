<?php
/**
 * Test cases for woocommerce.php in Web Worker Offloading.
 *
 * @package web-worker-offloading
 */

class Test_WooCommerce extends WP_UnitTestCase {

	/**
	 * Test the function that configures WWO for WooCommerce.
	 */
	public function test_plwwo_woocommerce_configure(): void {
		$configuration          = array();
		$expected_configuration = array(
			'globalFns' => array( 'gtag' ),
			'forward'   => array( 'dataLayer.push', 'gtag' ),
		);

		$result = plwwo_woocommerce_configure( $configuration );

		$this->assertEquals( $expected_configuration, $result );
	}
}
