<?php
return static function ( Test_Image_Prioritizer_Helper $test_case ): void {
	$breakpoint_max_widths = array( 480, 600, 782 );

	add_filter(
		'od_breakpoint_max_widths',
		static function () use ( $breakpoint_max_widths ) {
			return $breakpoint_max_widths;
		}
	);

	$test_case->populate_url_metrics(
		array(
			array(
				'xpath' => '/HTML/BODY/DIV/*[1][self::PICTURE]/*[2][self::IMG]',
				'isLCP' => true,
			),
		)
	);
};
