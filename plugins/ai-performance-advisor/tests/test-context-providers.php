<?php
/**
 * Tests for context providers and the registry.
 *
 * @package ai-performance-advisor
 */

declare( strict_types = 1 );

class AIPA_Test_Context_Providers extends WP_UnitTestCase {

	public function tear_down(): void {
		delete_option( 'aipa_settings' );
		delete_transient( AIPA_Provider_PageSpeed::CACHE_KEY );
		parent::tear_down();
	}

	public function test_environment_provider_shape(): void {
		$provider = new AIPA_Provider_Environment();
		$this->assertSame( 'environment', $provider->get_key() );
		$this->assertTrue( $provider->is_available() );

		$data = $provider->collect();
		$this->assertArrayHasKey( 'wp_version', $data );
		$this->assertArrayHasKey( 'php_version', $data );
		$this->assertArrayHasKey( 'active_theme', $data );
		$this->assertArrayHasKey( 'active_plugins', $data );
		$this->assertIsArray( $data['active_plugins'] );
	}

	public function test_site_health_provider_excludes_private_fields(): void {
		$provider = new AIPA_Provider_Site_Health();
		$data     = $provider->collect();

		// Flatten all collected values and ensure no obvious secret leaked.
		$flat = wp_json_encode( $data );
		$this->assertIsString( $flat );
		if ( defined( 'AUTH_KEY' ) && '' !== AUTH_KEY ) {
			$this->assertStringNotContainsString( (string) AUTH_KEY, $flat );
		}
	}

	public function test_pagespeed_provider_availability_follows_setting(): void {
		$provider = new AIPA_Provider_PageSpeed();

		update_option( 'aipa_settings', array( 'include_pagespeed' => true ) );
		$this->assertTrue( $provider->is_available() );

		update_option( 'aipa_settings', array( 'include_pagespeed' => false ) );
		$this->assertFalse( $provider->is_available() );
	}

	public function test_registry_collect_and_filter(): void {
		$registry = new AIPA_Context_Provider_Registry();
		$registry->register( new AIPA_Provider_Environment() );

		$context = $registry->collect();
		$this->assertArrayHasKey( 'environment', $context );

		// The aipa_context filter can adjust the payload.
		add_filter(
			'aipa_context',
			static function ( array $ctx ): array {
				$ctx['injected'] = array( 'hello' => 'world' );
				return $ctx;
			}
		);
		$filtered = $registry->collect();
		$this->assertArrayHasKey( 'injected', $filtered );
		remove_all_filters( 'aipa_context' );
	}

	public function test_registry_unregister(): void {
		$registry = new AIPA_Context_Provider_Registry();
		$registry->register( new AIPA_Provider_Environment() );
		$registry->unregister( 'environment' );
		$this->assertArrayNotHasKey( 'environment', $registry->get_available_providers() );
	}

	public function test_default_registry_includes_expected_providers(): void {
		$labels = aipa_get_context_registry()->get_available_labels();
		$this->assertNotEmpty( $labels );
	}
}
