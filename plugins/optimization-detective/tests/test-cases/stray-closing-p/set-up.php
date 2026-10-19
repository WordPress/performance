<?php
return static function ( Test_OD_Optimization $test_case ): void {
	add_filter( 'od_current_url_metrics_etag_data', '__return_empty_array' );

	/*
	 * These are the XPaths which a browser computes for the images in buffer.html. Note that the index for the
	 * hero image accounts for the empty P element which the HTML spec implies at the position of the stray
	 * closing P tag, the opening P tag having already been implicitly closed by the first FIGURE.
	 */
	$test_case->populate_url_metrics(
		array(
			array(
				'xpath' => '/HTML/BODY/DIV[@id=\'page\']/*[2][self::FIGURE]/*[1][self::IMG]',
				'isLCP' => false,
			),
			array(
				'xpath' => '/HTML/BODY/DIV[@id=\'page\']/*[4][self::FIGURE]/*[1][self::IMG]',
				'isLCP' => false,
			),
			array(
				'xpath' => '/HTML/BODY/DIV[@id=\'page\']/*[6][self::IMG]',
				'isLCP' => true,
			),
		),
		false
	);

	add_action(
		'od_register_tag_visitors',
		static function ( OD_Tag_Visitor_Registry $registry ): void {
			$registry->register(
				'img-lcp',
				static function ( OD_Tag_Visitor_Context $context ): bool {
					if ( 'IMG' !== $context->processor->get_tag() ) {
						return false;
					}

					// This only sets the attribute if the XPath computed for the tag matches the one stored in the URL Metrics.
					$lcp_element = $context->url_metric_group_collection->get_common_lcp_element();
					if ( null !== $lcp_element && $lcp_element->get_xpath() === $context->processor->get_xpath() ) {
						$context->processor->set_attribute( 'fetchpriority', 'high' );
					}
					return true;
				}
			);
		}
	);
};
