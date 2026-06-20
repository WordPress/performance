<?php
return static function ( Test_Image_Prioritizer_Helper $test_case ): void {
	$test_case->populate_url_metrics(
		array(
			array(
				// Note: This is intentionally using old XPath scheme to test the behavior in case an old URL Metric ends up getting used.
				// In practice, this should behave identically to the common-lcp-image-with-stale-sample-data test.
				'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::DIV]/*[1][self::IMG]',
				'isLCP' => true,
			),
		),
		false
	);
};
