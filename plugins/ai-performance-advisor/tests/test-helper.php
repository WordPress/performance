<?php
/**
 * Tests for helper functions.
 *
 * @package ai-performance-advisor
 */

declare( strict_types = 1 );

class AIPA_Test_Helper extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( 'aipa_settings' );
		remove_all_filters( 'aipa_pre_is_ai_available' );
		remove_all_actions( 'aipa_register_context_providers' );
		parent::tear_down();
	}

	/**
	 * @covers ::aipa_is_ai_available
	 */
	public function test_is_ai_available_is_false_in_test_environment(): void {
		// The AI Client API is not configured in the test environment.
		$this->assertFalse( aipa_is_ai_available() );
	}

	/**
	 * @covers ::aipa_is_ai_available
	 */
	public function test_is_ai_available_can_be_forced_on(): void {
		add_filter( 'aipa_pre_is_ai_available', '__return_true' );
		$this->assertTrue( aipa_is_ai_available() );
	}

	/**
	 * @covers ::aipa_is_ai_available
	 */
	public function test_is_ai_available_can_be_forced_off(): void {
		add_filter( 'aipa_pre_is_ai_available', '__return_false' );
		$this->assertFalse( aipa_is_ai_available() );
	}

	/**
	 * @covers ::aipa_get_default_settings
	 */
	public function test_default_settings(): void {
		$defaults = aipa_get_default_settings();
		$this->assertArrayHasKey( 'include_pagespeed', $defaults );
		$this->assertArrayHasKey( 'pagespeed_api_key', $defaults );
		$this->assertTrue( $defaults['include_pagespeed'] );
		$this->assertSame( '', $defaults['pagespeed_api_key'] );
	}

	/**
	 * @covers ::aipa_get_settings
	 */
	public function test_get_settings_merges_defaults(): void {
		update_option( 'aipa_settings', array( 'include_pagespeed' => false ) );
		$settings = aipa_get_settings();
		$this->assertFalse( $settings['include_pagespeed'] );
		$this->assertSame( '', $settings['pagespeed_api_key'] );
	}

	/**
	 * @covers ::aipa_get_settings
	 */
	public function test_get_settings_tolerates_non_array_option(): void {
		update_option( 'aipa_settings', 'not-an-array' );
		$settings = aipa_get_settings();
		$this->assertTrue( $settings['include_pagespeed'] );
		$this->assertSame( '', $settings['pagespeed_api_key'] );
	}

	/**
	 * @covers ::aipa_get_severities
	 * @covers ::aipa_get_categories
	 */
	public function test_severities_and_categories(): void {
		$this->assertSame( array( 'critical', 'recommended', 'good', 'info' ), aipa_get_severities() );
		$this->assertContains( 'images', aipa_get_categories() );
		$this->assertContains( 'other', aipa_get_categories() );
	}

	/**
	 * @covers ::aipa_sanitize_recommendations
	 * @covers ::aipa_sanitize_recommendation_action
	 */
	public function test_sanitize_recommendations_returns_empty_for_non_array(): void {
		$this->assertSame( array(), aipa_sanitize_recommendations( 'nope' ) );
		$this->assertSame( array(), aipa_sanitize_recommendations( null ) );
	}

	/**
	 * @covers ::aipa_sanitize_recommendations
	 * @covers ::aipa_sanitize_recommendation_action
	 */
	public function test_sanitize_recommendations_drops_invalid_and_sorts(): void {
		$raw = array(
			'not-an-array-item',
			array(
				'title'    => 'Low priority tip',
				'summary'  => 'Do this eventually.',
				'severity' => 'info',
				'category' => 'other',
			),
			array(
				// Missing summary, should be dropped.
				'title' => 'Incomplete',
			),
			array(
				'title'    => 'Urgent fix',
				'summary'  => 'Fix this now.',
				'severity' => 'critical',
				'category' => 'images',
				'evidence' => array( 'LCP is 5s', '', 42 ),
				'details'  => 'Use a **CDN** and <script>alert(1)</script> remove blocking JS.',
			),
			array(
				'title'    => 'Bad severity',
				'summary'  => 'Defaults applied.',
				'severity' => 'not-a-severity',
				'category' => 'not-a-category',
			),
		);

		$clean = aipa_sanitize_recommendations( $raw );

		$this->assertCount( 3, $clean );
		// Critical sorts first.
		$this->assertSame( 'Urgent fix', $clean[0]['title'] );
		$this->assertSame( 'critical', $clean[0]['severity'] );
		// Only valid, non-empty string evidence lines are kept.
		$this->assertSame( array( 'LCP is 5s' ), $clean[0]['evidence'] );
		// Details are run through wp_kses_post, stripping the script tag.
		$this->assertStringNotContainsString( '<script>', $clean[0]['details'] );
		$this->assertStringContainsString( 'CDN', $clean[0]['details'] );
		// An id is derived from the title (via sanitize_key) when not supplied.
		$this->assertSame( 'urgentfix', $clean[0]['id'] );

		// Invalid severity/category fall back to defaults.
		$bad = $this->find_by_title( $clean, 'Bad severity' );
		$this->assertSame( 'recommended', $bad['severity'] );
		$this->assertSame( 'other', $bad['category'] );
	}

	/**
	 * @covers ::aipa_sanitize_recommendations
	 */
	public function test_sanitize_recommendations_id_falls_back_when_title_has_no_word_chars(): void {
		$raw   = array(
			array(
				'title'   => '%%%',
				'summary' => 'Title sanitizes to an empty key.',
			),
		);
		$clean = aipa_sanitize_recommendations( $raw );
		$this->assertCount( 1, $clean );
		$this->assertSame( 'recommendation', $clean[0]['id'] );
	}

	/**
	 * @covers ::aipa_sanitize_recommendations
	 */
	public function test_sanitize_recommendations_honors_explicit_id(): void {
		$raw   = array(
			array(
				'id'      => 'Enable Caching!',
				'title'   => 'Caching',
				'summary' => 'Turn on caching.',
			),
		);
		$clean = aipa_sanitize_recommendations( $raw );
		$this->assertSame( 'enablecaching', $clean[0]['id'] );
	}

	/**
	 * @covers ::aipa_sanitize_recommendations
	 */
	public function test_sanitize_recommendations_unwraps_object(): void {
		$raw = array(
			'recommendations' => array(
				array(
					'title'   => 'Wrapped',
					'summary' => 'Inside a recommendations key.',
				),
			),
		);

		$clean = aipa_sanitize_recommendations( $raw );
		$this->assertCount( 1, $clean );
		$this->assertSame( 'Wrapped', $clean[0]['title'] );
	}

	/**
	 * @covers ::aipa_sanitize_recommendation_action
	 */
	public function test_sanitize_action_returns_null_for_non_array(): void {
		$raw   = array(
			array(
				'title'   => 'No action',
				'summary' => 'Has a scalar action value.',
				'action'  => 'nonsense',
			),
		);
		$clean = aipa_sanitize_recommendations( $raw );
		$this->assertNull( $clean[0]['action'] );
	}

	/**
	 * @covers ::aipa_sanitize_recommendation_action
	 */
	public function test_sanitize_action_keeps_admin_url_drops_unregistered_ability(): void {
		$raw = array(
			array(
				'title'   => 'With action',
				'summary' => 'Has an action payload.',
				'action'  => array(
					'settings_url' => admin_url( 'options-general.php' ),
					'ability'      => array(
						'name' => 'nonexistent/ability',
						'args' => array( 'foo' => 'bar' ),
					),
				),
			),
		);

		$clean = aipa_sanitize_recommendations( $raw );
		$this->assertNotNull( $clean[0]['action'] );
		$this->assertSame( admin_url( 'options-general.php' ), $clean[0]['action']['settings_url'] );
		// The ability is not registered, so it must not be retained.
		$this->assertNull( $clean[0]['action']['ability'] );
	}

	/**
	 * @covers ::aipa_sanitize_recommendation_action
	 */
	public function test_sanitize_action_rejects_offsite_url(): void {
		$raw = array(
			array(
				'title'   => 'Offsite',
				'summary' => 'Has an off-site settings URL.',
				'action'  => array(
					'settings_url' => 'https://evil.example.com/',
				),
			),
		);

		$clean = aipa_sanitize_recommendations( $raw );
		// Off-site URL is rejected and there is no ability, so action becomes null.
		$this->assertNull( $clean[0]['action'] );
	}

	/**
	 * @covers ::aipa_get_context_hash
	 */
	public function test_context_hash_is_stable_and_value_sensitive(): void {
		$a = aipa_get_context_hash(
			array(
				'a' => 1,
				'b' => 2,
			)
		);
		$b = aipa_get_context_hash(
			array(
				'a' => 1,
				'b' => 2,
			)
		);
		$c = aipa_get_context_hash(
			array(
				'a' => 1,
				'b' => 3,
			)
		);
		$this->assertSame( $a, $b );
		$this->assertNotSame( $a, $c );
		$this->assertSame( 32, strlen( $a ) );
	}

	/**
	 * @covers ::aipa_get_context_registry
	 */
	public function test_context_registry_has_default_providers_and_fires_action(): void {
		$fired = false;
		add_action(
			'aipa_register_context_providers',
			static function ( $registry ) use ( &$fired ): void {
				$fired = true;
				if ( $registry instanceof AIPA_Context_Provider_Registry ) {
					$registry->register( new AIPA_Provider_Environment() );
				}
			}
		);

		$registry = aipa_get_context_registry();
		$this->assertTrue( $fired );
		$this->assertArrayHasKey( 'environment', $registry->get_available_providers() );
	}

	/**
	 * Returns the first cleaned recommendation matching the given title.
	 *
	 * @param array<int, array<string, mixed>> $recommendations Cleaned recommendations.
	 * @param string                           $title           Title to find.
	 * @return array<string, mixed> Matching recommendation.
	 */
	private function find_by_title( array $recommendations, string $title ): array {
		foreach ( $recommendations as $recommendation ) {
			if ( $title === $recommendation['title'] ) {
				return $recommendation;
			}
		}
		$this->fail( "No recommendation titled {$title}." );
	}
}
