<?php
return static function ( Test_Image_Prioritizer_Helper $test_case ): void {
	$outside_viewport_rect = array_merge(
		$test_case->get_sample_dom_rect(),
		array(
			'top' => 100000,
		)
	);

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
				'intersectionRect'   => $outside_viewport_rect,
				'boundingClientRect' => $outside_viewport_rect,
			),
			array(
				'xpath'              => '/HTML/BODY/DIV[@id=\'page\']/*[4][self::DIV]',
				'isLCP'              => false,
				'intersectionRatio'  => 0.0,
				'intersectionRect'   => $outside_viewport_rect,
				'boundingClientRect' => $outside_viewport_rect,
			),
		)
	);
};
