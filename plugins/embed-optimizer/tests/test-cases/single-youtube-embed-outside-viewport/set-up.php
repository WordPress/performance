<?php
return static function ( Test_Embed_Optimizer_Optimization_Detective $test_case ): void {
	$test_case->populate_url_metrics(
		array(
			array(
				'xpath'                     => '/HTML/BODY/DIV/*[1][self::FIGURE]/*[1][self::DIV]',
				'isLCP'                     => false,
				'intersectionRatio'         => 0,
				'resizedBoundingClientRect' => array_merge( $test_case->get_sample_dom_rect(), array( 'height' => 500 ) ),
			),
		)
	);
};
