<?php
/**
 * Environment context provider.
 *
 * @package ai-performance-advisor
 *
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides active plugins, the active theme, and core version information.
 *
 * @since 1.0.0
 */
class AIPA_Provider_Environment extends AIPA_Context_Provider {

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @return string Provider key.
	 */
	public function get_key(): string {
		return 'environment';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @return string Provider label.
	 */
	public function get_label(): string {
		return __( 'Active plugins, active theme, and WordPress/PHP/database versions', 'ai-performance-advisor' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Environment data.
	 */
	public function collect(): array {
		global $wp_version, $wpdb;

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active_plugin_files = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active_plugin_files = array_merge( $active_plugin_files, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}

		$all_plugins = get_plugins();
		$plugins     = array();
		foreach ( array_unique( $active_plugin_files ) as $plugin_file ) {
			if ( isset( $all_plugins[ $plugin_file ] ) ) {
				$plugins[] = array(
					'name'    => $all_plugins[ $plugin_file ]['Name'],
					'version' => $all_plugins[ $plugin_file ]['Version'],
				);
			}
		}

		$theme        = wp_get_theme();
		$theme_data   = array(
			'name'    => $theme->get( 'Name' ),
			'version' => $theme->get( 'Version' ),
		);
		$parent_theme = $theme->parent();
		if ( $parent_theme instanceof WP_Theme ) {
			$theme_data['parent'] = array(
				'name'    => $parent_theme->get( 'Name' ),
				'version' => $parent_theme->get( 'Version' ),
			);
		}

		return array(
			'wp_version'     => (string) $wp_version,
			'php_version'    => PHP_VERSION,
			'database'       => $wpdb instanceof wpdb ? $wpdb->db_server_info() : '',
			'is_multisite'   => is_multisite(),
			'locale'         => get_locale(),
			'active_theme'   => $theme_data,
			'active_plugins' => $plugins,
		);
	}
}
