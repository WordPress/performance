<?php
return static function ( Test_OD_Optimization $test_case ): void {
	$test_case->populate_url_metrics(
		array(
			array(
				'xpath' => '/HTML/BODY/DIV/*[1][self::VIDEO]',
				'isLCP' => true,
			),
		),
		false
	);
};
