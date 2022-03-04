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
	 * Tests budget_calc_page_content() displays the settings form.
	 */
	public function test_budget_calc_page_content_displays_settings_form() {
		// Register the settings fields first.
		budget_calc_register_settings();

		ob_start();
		budget_calc_page_content();

		$output = ob_get_clean();
		$html   = new DOMDocument();
		$html->loadHTML( $output );

		// Assert the form exists and has the right method & action set.
		$this->assert_html_element_by_tag(
			$html,
			'form',
			array(
				'method' => 'post',
				'action' => 'options.php',
			)
		);

		// Assert all range fields are added to the form.
		$this->assert_html_element_by_id( $html, 'html_range', 'input' );
		$this->assert_html_element_by_id( $html, 'css_range', 'input' );
		$this->assert_html_element_by_id( $html, 'font_range', 'input' );
		$this->assert_html_element_by_id( $html, 'images_range', 'input' );
		$this->assert_html_element_by_id( $html, 'javascript_range', 'input' );

		// Assert a nonce field has been added.
		$this->assert_html_element_by_id(
			$html,
			'_wpnonce',
			'input',
			array(
				'type' => 'hidden',
				'name' => '_wpnonce',
			)
		);
	}

	/**
	 * Tests budget_calc_sanitize_settings() to verify that data input is properly sanitized before DB insertion.
	 */
	public function test_budget_calc_sanitize_settings() {
		$raw_input = array(
			'html_range'       => '100',
			'css_range'        => '200',
			'font_range'       => '300',
			'images_range'     => '400',
			'javascript_range' => '500',
			'updated'          => '1646294902',
		);

		$sanitized_input = budget_calc_sanitize_settings( $raw_input );

		$this->assertCount( 6, $sanitized_input );
		foreach ( array_keys( $raw_input ) as $input_name ) {
			$this->assertArrayHasKey( $input_name, $sanitized_input );
			$this->assertIsInt( $sanitized_input[ $input_name ] );
		}
	}

	/**
	 * Tests budget_calc_render_range_field() will output an HTML range field with all its expected attributes.
	 */
	public function test_budget_calc_render_range_field_output_html() {
		$options  = array( 'html_range' => '100' );
		$field_id = 'html_range';
		$max      = 1000;

		ob_start();
		budget_calc_render_range_field( $options, $field_id, $max );

		$output = ob_get_clean();
		$html   = new DOMDocument();
		$html->loadHTML( $output );

		$this->assert_html_element_by_id(
			$html,
			'html_range',
			'input',
			array(
				'type'    => 'range',
				'name'    => 'budget_calc_options[html_range]',
				'min'     => '0',
				'max'     => '1000',
				'step'    => '10',
				'value'   => '100',
				'oninput' => "document.getElementById('html_range_output').textContent=this.value+'KB'",
			)
		);

		$this->assert_html_element_by_id( $html, 'html_range_output', 'span' );
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

	/**
	 * Look for an HTML element by ID and assert it is rendered as we expect.
	 *
	 * @param DOMDocument $html       The parsed HTML containing the HTML element.
	 * @param string      $element_id The ID of the element to assert.
	 * @param string      $tag_name   The tag name of the element to assert.
	 * @param array       $attributes Any element attribute we want to validate.
	 */
	protected function assert_html_element_by_id( $html, $element_id, $tag_name, $attributes = array() ) {
		$element = $html->getElementById( $element_id );
		$this->assertNotNull( $element );

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$this->assertSame( $tag_name, $element->tagName );

		foreach ( $attributes as $attribute_name => $attribute_value ) {
			$this->assertSame( $attribute_value, $element->getAttribute( $attribute_name ) );
		}
	}

	/**
	 * Look for an HTML element by tag name and assert it is rendered as we expect.
	 *
	 * @param DOMDocument $html       The parsed HTML containing the HTML element.
	 * @param string      $tag_name   The tag name of the element to assert.
	 * @param array       $attributes Any element attribute we want to validate.
	 */
	protected function assert_html_element_by_tag( $html, $tag_name, $attributes = array() ) {
		$elements = $html->getElementsByTagName( $tag_name );

		$this->assertNotEmpty( $elements );
		// There could be more than one element with a specific tag name, assume we want to check the first one.
		$element = $elements->item( 0 );

		foreach ( $attributes as $attribute_name => $attribute_value ) {
			$this->assertSame( $attribute_value, $element->getAttribute( $attribute_name ) );
		}
	}
}
