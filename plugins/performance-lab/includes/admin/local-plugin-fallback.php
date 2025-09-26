<?php
/**
 * Local Plugin Fallback functionality for Performance Lab.
 *
 * Provides fallback functionality to show plugin cards for locally installed
 * performance-related plugins when external API requests are disabled or fail.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

// Ensure this file is loaded when Performance Lab plugin is active
if ( ! function_exists( 'perflab_get_local_plugin_fallback_data' ) ) {

/**
 * Gets local plugin information for Performance Lab standalone plugins.
 *
 * This function provides fallback data when external API requests to WordPress.org
 * are disabled or fail, allowing the Performance Lab interface to still show
 * cards for locally installed performance plugins.
 *
 * @since n.e.x.t
 *
 * @param string[] $plugin_slugs Array of plugin slugs to get local info for.
 * @return array<string, array{name: string, slug: string, short_description: string, requires: string|false, requires_php: string|false, requires_plugins: string[], version: string, is_installed: bool, is_active: bool}> Local plugin data keyed by slug.
 */
function perflab_get_local_plugin_fallback_data( array $plugin_slugs ): array {
	// Ensure we have access to plugin functions.
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$local_plugins = get_plugins();
	$fallback_data = array();

	foreach ( $plugin_slugs as $plugin_slug ) {
		// Look for plugin files that match this slug.
		$plugin_file = perflab_find_local_plugin_file( $local_plugins, $plugin_slug );

		if ( ! $plugin_file ) {
			continue; // Plugin not installed locally.
		}

		$plugin_headers = $local_plugins[ $plugin_file ];
		$is_active      = is_plugin_active( $plugin_file );

		// Build normalized plugin data similar to WordPress.org API response.
		$readme_description = perflab_get_plugin_readme_description( $plugin_file );
		$description        = $readme_description !== '' ? $readme_description : ( $plugin_headers['Description'] ?? '' );

		$fallback_data[ $plugin_slug ] = array(
			'name'              => $plugin_headers['Name'] ?? $plugin_slug,
			'slug'              => $plugin_slug,
			'short_description' => $description,
			'requires'          => $plugin_headers['RequiresWP'] ?? false,
			'requires_php'      => $plugin_headers['RequiresPHP'] ?? false,
			'requires_plugins'  => perflab_parse_requires_plugins( $plugin_headers, $plugin_slug ),
			'version'           => $plugin_headers['Version'] ?? '0.0.0',
			'is_installed'      => true,
			'is_active'         => $is_active,
		);
	}

	return $fallback_data;
}

/**
 * Finds the plugin file for a given slug among installed plugins.
 *
 * @since n.e.x.t
 *
 * @param array<string, array<string, string>> $local_plugins Array from get_plugins().
 * @param string                               $plugin_slug   Plugin slug to find.
 * @return string|false Plugin file path relative to plugins directory, or false if not found.
 */
function perflab_find_local_plugin_file( array $local_plugins, string $plugin_slug ) {
	foreach ( $local_plugins as $plugin_file => $plugin_data ) {
		// Extract directory name from plugin file path.
		$plugin_dir = strtok( $plugin_file, '/' );

		if ( $plugin_dir === $plugin_slug ) {
			return $plugin_file;
		}
	}

	return false;
}

/**
 * Gets plugin description from readme file.
 *
 * @since n.e.x.t
 *
 * @param string $plugin_file Plugin file path.
 * @return string Plugin description from readme or empty string.
 */
function perflab_get_plugin_readme_description( string $plugin_file ): string {
	if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
		return '';
	}

	$plugin_dir   = dirname( WP_PLUGIN_DIR . '/' . $plugin_file );
	$readme_files = array( 'readme.txt', 'README.txt', 'readme.md', 'README.md' );

	foreach ( $readme_files as $readme_file ) {
		$readme_path = $plugin_dir . '/' . $readme_file;
		if ( file_exists( $readme_path ) ) {
			$readme_content = file_get_contents( $readme_path );
			if ( $readme_content !== false ) {
				// Parse description from readme - look for description after "== Description ==".
				if ( preg_match( '/==\s*Description\s*==(.*?)(?==|\z)/is', $readme_content, $matches ) ) {
					$description = trim( $matches[1] ?? '' );
					// Remove markdown formatting and clean up.
					$description = preg_replace( '/\*\*(.*?)\*\*/', '$1', $description ) ?? $description;
					$description = preg_replace( '/\*(.*?)\*/', '$1', $description ) ?? $description;
					$description = preg_replace( '/\[([^\]]+)\]\([^\)]+\)/', '$1', $description ) ?? $description;
					return trim( $description );
				}
			}
		}
	}

	return '';
}

/**
 * Sanitizes plugin description for display.
 *
 * @since n.e.x.t
 *
 * @param string $description Raw plugin description.
 * @return string Sanitized description.
 */
function perflab_sanitize_plugin_description( string $description ): string {
	if ( $description === '' ) {
		return '';
	}

	// Strip all HTML tags and decode entities.
	$description = wp_strip_all_tags( $description );
	$description = html_entity_decode( $description, ENT_QUOTES, 'UTF-8' );

	return trim( $description );
}

/**
 * Parses the requires_plugins from plugin headers.
 *
 * This attempts to extract required plugins from various possible header formats.
 *
 * @since n.e.x.t
 *
 * @param array<string, string> $plugin_headers Plugin headers array.
 * @param string                $plugin_slug    Plugin slug to determine dependencies.
 * @return string[] Array of required plugin slugs.
 */
function perflab_parse_requires_plugins( array $plugin_headers, string $plugin_slug ): array {
	$requires_plugins = array();

	// Check for RequiresPlugins header (WordPress 6.5+).
	if ( isset( $plugin_headers['RequiresPlugins'] ) && $plugin_headers['RequiresPlugins'] !== '' ) {
		$plugins          = array_map( 'trim', explode( ',', $plugin_headers['RequiresPlugins'] ) );
		$requires_plugins = array_merge( $requires_plugins, $plugins );
	}

	// For known Performance Lab plugins, add their specific dependencies.
	// Embed Optimizer has a hard dependency on Optimization Detective.
	if ( 'embed-optimizer' === $plugin_slug ) {
		$requires_plugins[] = 'optimization-detective';
	}

	// Image Prioritizer has a hard dependency on Optimization Detective.
	if ( 'image-prioritizer' === $plugin_slug ) {
		$requires_plugins[] = 'optimization-detective';
	}

	return array_unique( array_filter( $requires_plugins ) );
}

/**
 * Checks if external requests are blocked or likely to fail.
 *
 * @since n.e.x.t
 *
 * @return bool True if external requests are blocked or should be avoided.
 */
function perflab_are_external_requests_blocked(): bool {
	// Check if external requests are explicitly blocked.
	if ( defined( 'WP_HTTP_BLOCK_EXTERNAL' ) && WP_HTTP_BLOCK_EXTERNAL ) {
		// Check if wordpress.org is in the allowed hosts.
		$allowed_hosts = defined( 'WP_ACCESSIBLE_HOSTS' ) ? WP_ACCESSIBLE_HOSTS : '';
		if ( $allowed_hosts === '' ) {
			return true;
		}

		$allowed_hosts_array = array_map( 'trim', explode( ',', $allowed_hosts ) );
		if ( ! in_array( 'wordpress.org', $allowed_hosts_array, true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Gets plugin settings URL for a given plugin slug.
 *
 * This attempts to determine the settings URL for locally installed performance plugins.
 *
 * @since n.e.x.t
 *
 * @param string $plugin_slug Plugin slug.
 * @return string|null Settings URL or null if not available.
 */
function perflab_get_local_plugin_settings_url( string $plugin_slug ): ?string {
	return perflab_get_plugin_settings_url( $plugin_slug );
}

} // Close the function_exists check
