<?php
return static function ( Test_Embed_Optimizer_Optimization_Detective $test_case ): void {

	$element_data = array(
		'isLCP'                     => false,
		'intersectionRatio'         => 1,
		'resizedBoundingClientRect' => array_merge( $test_case->get_sample_dom_rect(), array( 'height' => 500 ) ),
	);

	$elements = array();
	for ( $i = 1; $i < 10; $i++ ) {
		$elements[] = array_merge(
			$element_data,
			array(
				'xpath' => "/HTML/BODY/DIV/*[{$i}][self::FIGURE]/*[1][self::DIV]",
			)
		);
	}

	$test_case->populate_url_metrics( $elements );
};
