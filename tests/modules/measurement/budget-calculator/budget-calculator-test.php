<?php
/**
 * Tests for budget-calculator module.
 *
 * @package performance-lab
 * @group budget-calculator
 */

class Budget_Calculator_Tests extends WP_UnitTestCase {

	/**
	 * Tests budget_calc_menu_page() to check the menu is added.
	 */
	public function test_budget_calc_menu_page_is_added() {
		budget_calc_menu_page();

		global $menu;
		$calculator_menu_exists = false;

		// Look for the budget calculator menu across all added menus.
		foreach ( $menu as $menu_item ) {
			if ( array_search( 'budget-calculator', $menu_item, true ) ) {
				$calculator_menu_exists = true;
				break;
			}
		}

		$this->assertTrue( $calculator_menu_exists );
	}

	/**
	 * Tests budget_calc_format_for_budget_json() will format the data according to budget.json specification.
	 */
	public function test_budget_calc_format_for_budget_json() {
		$db_data = array(
			'html_range'       => 100,
			'css_range'        => 200,
			'font_range'       => 300,
			'images_range'     => 400,
			'javascript_range' => 500,
		);

		$expected_data = array(
			array(
				'resourceSizes' => array(
					array(
						'resourceType' => 'document',
						'budget'       => 100,
					),
					array(
						'resourceType' => 'stylesheet',
						'budget'       => 200,
					),
					array(
						'resourceType' => 'font',
						'budget'       => 300,
					),
					array(
						'resourceType' => 'image',
						'budget'       => 400,
					),
					array(
						'resourceType' => 'script',
						'budget'       => 500,
					),
				),
			),
		);

		$formatted_data = budget_calc_format_for_budget_json( $db_data );

		$this->assertSame( $expected_data, $formatted_data );
	}
}
