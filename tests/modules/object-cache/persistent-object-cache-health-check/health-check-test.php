<?php
/**
 * Tests for persistent-object-cache-health-check module.
 *
 * @package performance-lab
 * @group persistent-object-cache-health-check
 */

class Object_Cache_Health_Check_Tests extends WP_UnitTestCase {

	function test_object_cache_thresholds_check_is_bypassed() {
		$bypassed = true;

		add_filter(
			'site_status_persistent_object_cache_thresholds',
			function ( $thresholds ) use ( $bypassed ) {
				$bypassed = false;

				return $thresholds;
			}
		);

		$result = oc_health_should_persistent_object_cache( true );

		$this->assertTrue( $result );
		$this->assertTrue( $bypassed );

		remove_all_filters( 'site_status_persistent_object_cache_thresholds' );
	}

	function test_object_cache_default_thresholds() {
		$result = oc_health_should_persistent_object_cache( false );

		$this->assertFalse( $result );
	}

	function test_object_cache_comments_threshold() {
		add_filter(
			'site_status_persistent_object_cache_thresholds',
			function ( $thresholds ) {
				return array_merge( $thresholds, array( 'comments_count' => 0 ) );
			}
		);

		$result = oc_health_should_persistent_object_cache( false );

		$this->assertTrue( $result );

		remove_all_filters( 'site_status_persistent_object_cache_thresholds' );
	}

	function test_object_cache_posts_threshold() {
		add_filter(
			'site_status_persistent_object_cache_thresholds',
			function ( $thresholds ) {
				return array_merge( $thresholds, array( 'posts_count' => 0 ) );
			}
		);

		$result = oc_health_should_persistent_object_cache( false );

		$this->assertTrue( $result );

		remove_all_filters( 'site_status_persistent_object_cache_thresholds' );
	}

	function test_object_cache_terms_threshold() {
		add_filter(
			'site_status_persistent_object_cache_thresholds',
			function ( $thresholds ) {
				return array_merge( $thresholds, array( 'terms_count' => 1 ) );
			}
		);

		$result = oc_health_should_persistent_object_cache( false );

		$this->assertTrue( $result );

		remove_all_filters( 'site_status_persistent_object_cache_thresholds' );
	}

	function test_object_cache_options_threshold() {
		add_filter(
			'site_status_persistent_object_cache_thresholds',
			function ( $thresholds ) {
				return array_merge( $thresholds, array( 'options_count' => 100 ) );
			}
		);

		$result = oc_health_should_persistent_object_cache( false );

		$this->assertTrue( $result );

		remove_all_filters( 'site_status_persistent_object_cache_thresholds' );
	}

	function test_object_cache_users_threshold() {
		add_filter(
			'site_status_persistent_object_cache_thresholds',
			function ( $thresholds ) {
				return array_merge( $thresholds, array( 'users_count' => 0 ) );
			}
		);

		$result = oc_health_should_persistent_object_cache( false );

		$this->assertTrue( $result );

		remove_all_filters( 'site_status_persistent_object_cache_thresholds' );
	}

	function test_object_cache_alloptions_count_threshold() {
		add_filter(
			'site_status_persistent_object_cache_thresholds',
			function ( $thresholds ) {
				return array_merge( $thresholds, array( 'alloptions_count' => 100 ) );
			}
		);

		$result = oc_health_should_persistent_object_cache( false );

		$this->assertTrue( $result );

		remove_all_filters( 'site_status_persistent_object_cache_thresholds' );
	}

	function test_object_cache_alloptions_bytes_threshold() {
		add_filter(
			'site_status_persistent_object_cache_thresholds',
			function ( $thresholds ) {
				return array_merge( $thresholds, array( 'alloptions_bytes' => 1000 ) );
			}
		);

		$result = oc_health_should_persistent_object_cache( false );

		$this->assertTrue( $result );

		remove_all_filters( 'site_status_persistent_object_cache_thresholds' );
	}

}
