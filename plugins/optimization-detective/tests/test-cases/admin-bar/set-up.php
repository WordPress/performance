<?php
return static function ( Test_OD_Optimization $test_case ): void {
	add_action(
		'od_register_tag_visitors',
		static function ( OD_Tag_Visitor_Registry $registry ) use ( $test_case ): void {
			$registry->register(
				'everything',
				static function ( OD_Tag_Visitor_Context $context ) use ( $test_case ): void {
					$test_case->assertNotEquals( 'wpadminbar', $context->processor->get_attribute( 'id' ) );
					$test_case->assertNull( $context->url_metrics_id, 'Expected url_metrics_id to be null since no od_url_metrics_post exists for this URL.' );
				}
			);
		}
	);
};
