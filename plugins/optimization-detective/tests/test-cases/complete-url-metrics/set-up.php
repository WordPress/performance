<?php
return static function ( Test_OD_Optimization $test_case ): void {
	ini_set( 'default_mimetype', 'text/html; charset=utf-8' ); // phpcs:ignore WordPress.PHP.IniSet.Risky

	// Normalize the data for computing the current URL Metrics ETag to work around the issue where there is no
	// global variable storing the OD_Tag_Visitor_Registry instance along with any registered tag visitors, so
	// during set up we do not know what the ETag will look like. The current ETag is only established when
	// the output begins to be processed by od_optimize_template_output_buffer().
	add_filter( 'od_current_url_metrics_etag_data', '__return_empty_array' );

	$post_id = $test_case->populate_url_metrics(
		array(
			array(
				'xpath' => '/HTML/BODY/DIV/*[1][self::IMG]',
				'isLCP' => true,
			),
		)
	);

	add_action(
		'od_register_tag_visitors',
		static function ( OD_Tag_Visitor_Registry $registry ) use ( $test_case, $post_id ): void {
			$registry->register(
				'everything',
				static function ( OD_Tag_Visitor_Context $context ) use ( $test_case, $post_id ): void {
					$test_case->assertSame( $post_id, $context->url_metrics_post_id, 'Expected url_metrics_post_id in context too match the ID from when URL Metrics were populated.' );
				}
			);
		}
	);
};
