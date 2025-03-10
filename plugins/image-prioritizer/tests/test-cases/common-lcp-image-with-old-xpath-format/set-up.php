<?php
return static function ( Test_Image_Prioritizer_Helper $test_case ): void {
	$test_case->populate_url_metrics(
		array(
			array(
				// Note: This is intentionally using old XPath scheme. This is to make sure that the old format still results in the expected optimization during a transitional period.
				'xpath' => '/*[1][self::HTML]/*[2][self::BODY]/*[1][self::DIV]/*[1][self::IMG]',
				'isLCP' => true,
			),
		),
		false
	);
};
