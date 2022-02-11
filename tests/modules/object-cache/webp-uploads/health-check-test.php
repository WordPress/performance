<?php
/**
 * Tests for webp-uploads module.
 *
 * @package performance-lab
 * @group webp-uploads
 */

class Object_Cache_Health_Check_Tests extends WP_UnitTestCase {

	function test_object_cache_thresholds_check_is_bypassed() {
		$bypassed = true;

		add_filter( 'site_status_persistent_object_cache_thresholds', function () use ($bypassed) {
			$bypassed = false;
		} );

		$result = oc_health_should_persistent_object_cache( true );

		$this->assertTrue( $result );
		$this->assertTrue( $bypassed );
	}

	function test_object_cache_debug() {
		global $wpdb;

		$alloptions = wp_load_alloptions();

		var_dump(count( $alloptions ));

		var_dump(strlen( serialize( $alloptions ) ));

		$table_names = implode( "','", array( $wpdb->comments, $wpdb->options, $wpdb->posts, $wpdb->terms, $wpdb->users ) );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT TABLE_NAME AS 'table', TABLE_ROWS AS 'rows', SUM(data_length + index_length) as 'bytes'
				FROM information_schema.TABLES
				WHERE TABLE_SCHEMA = %s
				AND TABLE_NAME IN ('$table_names')
				GROUP BY TABLE_NAME;",
				DB_NAME
			),
			OBJECT_K
		);

		var_dump($results);
	}

}
