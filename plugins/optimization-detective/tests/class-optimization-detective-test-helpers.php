<?php
/**
 * Helper trait for Optimization Detective tests.
 *
 * @package optimization-detective
 *
 * @noinspection PhpDocMissingThrowsInspection
 * @noinspection PhpUnhandledExceptionInspection
 */

/**
 * @phpstan-type ElementDataSubset array{
 *     xpath: string,
 *     isLCP?: bool,
 *     intersectionRatio?: float
 * }
 */
trait Optimization_Detective_Test_Helpers {

	/**
	 * Populates complete URL metrics for the provided element data.
	 *
	 * @phpstan-param ElementDataSubset[] $elements
	 * @param array[] $elements Element data.
	 * @param bool    $complete Whether to fully populate the groups.
	 * @throws Exception But it won't.
	 */
	public function populate_url_metrics( array $elements, bool $complete = true ): void {
		$slug        = od_get_url_metrics_slug( od_get_normalized_query_vars() );
		$etag        = od_get_current_url_metrics_etag( new OD_Tag_Visitor_Registry(), null, null ); // Note: Tests rely on the od_current_url_metrics_etag_data filter to set the desired value.
		$sample_size = $complete ? od_get_url_metrics_breakpoint_sample_size() : 1;
		foreach ( array_merge( od_get_breakpoint_max_widths(), array( 1000 ) ) as $viewport_width ) {
			for ( $i = 0; $i < $sample_size; $i++ ) {
				OD_URL_Metrics_Post_Type::store_url_metric(
					$slug,
					$this->get_sample_url_metric(
						array(
							'etag'           => $etag,
							'viewport_width' => $viewport_width,
							'elements'       => $elements,
						)
					)
				);
			}
		}
	}

	/**
	 * Gets a sample DOM rect for testing.
	 *
	 * @return array<string, float>
	 */
	public function get_sample_dom_rect(): array {
		return array(
			'width'  => 500.1,
			'height' => 500.2,
			'x'      => 100.3,
			'y'      => 100.4,
			'top'    => 0.1,
			'right'  => 0.2,
			'bottom' => 0.3,
			'left'   => 0.4,
		);
	}

	/**
	 * Gets a sample URL metric.
	 *
	 * @phpstan-param array{
	 *                    timestamp?:       float,
	 *                    etag?:            non-empty-string,
	 *                    url?:             string,
	 *                    viewport_width?:  int,
	 *                    viewport_height?: int,
	 *                    element?:         ElementDataSubset,
	 *                    elements?:        array<ElementDataSubset>
	 *                } $params Params.
	 *
	 * @return OD_URL_Metric URL metric.
	 */
	public function get_sample_url_metric( array $params ): OD_URL_Metric {
		$params = array_merge(
			array(
				'etag'           => od_get_current_url_metrics_etag( new OD_Tag_Visitor_Registry(), null, null ), // Note: Tests rely on the od_current_url_metrics_etag_data filter to set the desired value.
				'url'            => home_url( '/' ),
				'viewport_width' => 480,
				'elements'       => array(),
				'timestamp'      => microtime( true ),
				'extended_root'  => array(),
			),
			$params
		);

		if ( array_key_exists( 'element', $params ) ) {
			$params['elements'][] = $params['element'];
		}

		$data = array_merge(
			array(
				'etag'      => $params['etag'],
				'url'       => $params['url'],
				'viewport'  => array(
					'width'  => $params['viewport_width'],
					'height' => $params['viewport_height'] ?? ceil( $params['viewport_width'] / 2 ),
				),
				'timestamp' => $params['timestamp'],
				'elements'  => array_map(
					function ( array $element ): array {
						return array_merge(
							array(
								'isLCP'              => false,
								'isLCPCandidate'     => $element['isLCP'] ?? false,
								'intersectionRatio'  => 1,
								'intersectionRect'   => $this->get_sample_dom_rect(),
								'boundingClientRect' => $this->get_sample_dom_rect(),
							),
							$element
						);
					},
					$params['elements']
				),
			),
			$params['extended_root']
		);
		return new OD_URL_Metric( $data );
	}

	/**
	 * Removes initial tabs from the lines in the input.
	 *
	 * @param string $input Input.
	 * @return string Output.
	 */
	public function remove_initial_tabs( string $input ): string {
		return (string) preg_replace( '/^\t+/m', '', $input );
	}

	/**
	 * Gets JSON-serializable data from an array of JsonSerializable objects.
	 *
	 * @param JsonSerializable[] $items Items.
	 * @return array<string|int, mixed> Data from items.
	 */
	public function get_array_json_data( array $items ): array {
		return array_map(
			static function ( JsonSerializable $item ) {
				return $item->jsonSerialize();
			},
			$items
		);
	}

	/**
	 * Loads snapshot test cases.
	 *
	 * @param non-empty-string $directory Directory for test cases.
	 * @return array<string, array{ directory: non-empty-string }> Test cases.
	 */
	public function load_snapshot_test_cases( string $directory ): array {
		$test_cases = array();
		foreach ( (array) glob( $directory . '/*' ) as $test_case ) {
			/**
			 * Test case directory.
			 *
			 * @var non-empty-string $test_case
			 */
			$test_cases[ basename( $test_case ) ] = array(
				'directory' => $test_case,
			);
		}
		return $test_cases;
	}

	/**
	 * Asserts equality against snapshot.
	 *
	 * @param non-empty-string $directory Directory for test cases.
	 */
	public function assert_snapshot_equals( string $directory ): void {
		$set_up_file = "$directory/set-up.php";
		if ( ! file_exists( $set_up_file ) ) {
			throw new Exception( "Missing required file: $directory/set-up.php" );
		}

		$set_up      = require $set_up_file;
		$buffer_file = "$directory/buffer.html";

		$buffer = file_get_contents( $buffer_file );
		if ( ! is_string( $buffer ) ) {
			throw new Exception( "Missing test case file: $buffer_file" );
		}

		$expected_file = "$directory/expected.html";
		$actual_file   = "$directory/actual.html";
		if ( file_exists( $expected_file ) ) {
			$expected = file_get_contents( $expected_file );
			if ( ! is_string( $expected ) ) {
				throw new Exception( "Missing test case file: $expected_file" );
			}
		} else {
			$expected = null;
		}

		$replacements = $set_up( $this, $this::factory() );

		// Replace placeholders with values computed in set_up.
		if ( is_array( $replacements ) && count( $replacements ) > 0 ) {
			$buffer = str_replace( array_keys( $replacements ), array_values( $replacements ), $buffer );
			if ( is_string( $expected ) ) {
				$expected = str_replace( array_keys( $replacements ), array_values( $replacements ), $expected );
			}
		}

		$buffer = od_optimize_template_output_buffer( $buffer );

		// Normalize script module content so changes do not impact snapshots.
		$buffer = preg_replace_callback(
			'#(<script type="module">)(.+?)(</script>)#s',
			static function ( $matches ) {
				array_shift( $matches );
				list( $start_tag, $text, $end_tag ) = $matches;

				$text = str_replace( "/* <![CDATA[ */\n", '', $text );
				$text = str_replace( "/* ]]> */\n", '', $text );
				$text = trim( $text );
				if ( 1 === preg_match( '/^(import|const) \w+/', $text, $matches ) ) {
					$text = '/* ' . $matches[0] . ' ... */';
				}
				return $start_tag . $text . $end_tag;
			},
			$buffer
		);

		// Undo replacements so that the placeholders are restored to the buffer for persisting in the snapshot.
		$snapshot = $buffer;
		if ( is_array( $replacements ) && count( $replacements ) > 0 ) {
			$snapshot = str_replace( array_values( $replacements ), array_keys( $replacements ), $snapshot );
		}

		$buffer = $this->remove_initial_tabs( $buffer );
		if ( is_string( $expected ) ) {
			$expected = $this->remove_initial_tabs( $expected );
		}

		if ( $buffer !== $expected ) {
			file_put_contents( $actual_file, $snapshot ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		$rel_actual_file   = str_replace( trailingslashit( getcwd() ), '', $actual_file );
		$rel_expected_file = str_replace( trailingslashit( getcwd() ), '', $expected_file );
		$rel_buffer_file   = str_replace( trailingslashit( getcwd() ), '', $buffer_file );

		if ( null === $expected ) {
			$this->markTestIncomplete( "Examine $actual_file to see if it is expected and if so rename to $expected_file." );
		} else {
			$this->assertEquals(
				$expected,
				$buffer,
				"Examine $rel_actual_file for differences with $rel_buffer_file and if acceptable move to $rel_expected_file"
			);
		}
	}
}
