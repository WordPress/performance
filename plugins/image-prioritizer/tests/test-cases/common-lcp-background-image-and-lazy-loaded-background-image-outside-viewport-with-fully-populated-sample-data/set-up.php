<?php
return static function ( Test_Image_Prioritizer_Helper $test_case ): void {
	$test_case->populate_url_metrics(
		array(
			array(
				'xpath' => '/HTML/BODY/DIV[@id=\'page\']/*[1][self::DIV]',
				'isLCP' => true,
			),
			array(
				'xpath'              => '/HTML/BODY/DIV[@id=\'page\']/*[3][self::DIV]',
				'isLCP'              => false,
				'intersectionRatio'  => 0.0,
				'boundingClientRect' => array( 'top' => 100000 ),
			),
			array(
				'xpath'              => '/HTML/BODY/DIV[@id=\'page\']/*[4][self::DIV]',
				'isLCP'              => false,
				'intersectionRatio'  => 0.0,
				'boundingClientRect' => array( 'top' => 100000 ),
			),
		)
	);
};
