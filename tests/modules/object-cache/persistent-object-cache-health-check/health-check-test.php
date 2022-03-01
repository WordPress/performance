<?php
/**
 * Tests for persistent-object-cache-health-check module.
 *
 * @package performance-lab
 * @group persistent-object-cache-health-check
 */

class Object_Cache_Health_Check_Tests extends WP_UnitTestCase {

	/**
	 * @group ms-excluded
	 */
	function test_object_cache_default_thresholds() {
		$this->assertFalse(
			perflab_oc_health_should_suggest_persistent_object_cache()
		);
	}

	/**
	 * @group ms-required
	 */
	function test_object_cache_default_thresholds_on_multisite() {
		$this->assertTrue(
			perflab_oc_health_should_suggest_persistent_object_cache()
		);
	}

	function test_object_cache_thresholds_check_can_be_bypassed() {
		add_filter( 'perflab_oc_site_status_suggest_persistent_object_cache', '__return_true' );

		$this->assertTrue(
			perflab_oc_health_should_suggest_persistent_object_cache()
		);
	}

	/**
	 * @dataProvider thresholds
	 */
	function test_object_cache_thresholds( $threshold, $count ) {
		add_filter(
			'perflab_oc_site_status_persistent_object_cache_thresholds',
			function ( $thresholds ) use ( $threshold, $count ) {
				return array_merge( $thresholds, array( $threshold => $count ) );
			}
		);

		$this->assertTrue(
			perflab_oc_health_should_suggest_persistent_object_cache()
		);
	}

	function thresholds() {
		return array(
			array( 'comments_count', 0 ),
			array( 'posts_count', 0 ),
			array( 'terms_count', 1 ),
			array( 'options_count', 100 ),
			array( 'users_count', 0 ),
			array( 'alloptions_count', 100 ),
			array( 'alloptions_bytes', 1000 ),
		);
	}
}
