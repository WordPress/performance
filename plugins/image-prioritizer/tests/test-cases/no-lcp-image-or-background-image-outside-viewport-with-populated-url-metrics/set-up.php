<?php
return static function ( Test_Image_Prioritizer_Helper $test_case ): void {
	$test_case->populate_url_metrics(
		array(
			array(
				'xpath' => '/HTML/BODY/DIV/*[1][self::H1]',
				'isLCP' => true,
			),
		)
	);
};
