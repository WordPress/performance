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
		parent::tear_down();
	}

	public function test_default_settings(): void {
		$defaults = aipa_get_default_settings();
		$this->assertArrayHasKey( 'include_pagespeed', $defaults );
		$this->assertArrayHasKey( 'pagespeed_api_key', $defaults );
		$this->assertTrue( $defaults['include_pagespeed'] );
	}

	public function test_get_settings_merges_defaults(): void {
		update_option( 'aipa_settings', array( 'include_pagespeed' => false ) );
		$settings = aipa_get_settings();
		$this->assertFalse( $settings['include_pagespeed'] );
		$this->assertSame( '', $settings['pagespeed_api_key'] );
	}

	public function test_is_ai_available_false_when_unsupported(): void {
		add_filter( 'wp_supports_ai', '__return_false' );
		$this->assertFalse( aipa_is_ai_available() );
		remove_filter( 'wp_supports_ai', '__return_false' );
	}

	public function test_sanitize_recommendations_drops_invalid_and_sorts(): void {
		$raw = array(
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
				'evidence' => array( 'LCP is 5s', '' ),
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
		// Empty evidence line is dropped.
		$this->assertSame( array( 'LCP is 5s' ), $clean[0]['evidence'] );
		// Invalid severity/category fall back to defaults.
		$bad = array_values(
			array_filter(
				$clean,
				static function ( array $r ): bool {
					return 'Bad severity' === $r['title'];
				}
			)
		);
		$this->assertSame( 'recommended', $bad[0]['severity'] );
		$this->assertSame( 'other', $bad[0]['category'] );
	}

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
}
