<?php
/**
 * Plugin Management Module
 *
 * Enhancement: Show local plugin cards if external plugin data fails to load.
 *
 * @package performance-lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Display plugin cards from remote or local sources.
 */
function performance_lab_render_plugin_cards() {
	// Try to get plugin data from WordPress.org API
	$response = wp_remote_get( 'https://api.wordpress.org/plugins/info/1.2/?action=query_plugins' );

	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		// 🟠 Fallback: show local plugins if remote request fails
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$all_plugins = get_plugins();

		if ( empty( $all_plugins ) ) {
			echo '<p>No plugins found.</p>';
			return;
		}

		echo '<div class="plugin-cards">';
		foreach ( $all_plugins as $plugin_path => $plugin_data ) {
			echo '<div class="plugin-card" style="border:1px solid #ddd; padding:15px; margin:10px; border-radius:8px;">';
			echo '<h3>' . esc_html( $plugin_data['Name'] ) . '</h3>';
			if ( ! empty( $plugin_data['Version'] ) ) {
				echo '<p><strong>Version:</strong> ' . esc_html( $plugin_data['Version'] ) . '</p>';
			}
			if ( ! empty( $plugin_data['Description'] ) ) {
				echo '<p>' . esc_html( $plugin_data['Description'] ) . '</p>';
			}
			echo '</div>';
		}
		echo '</div>';

		return;
	}

	// ✅ If external request is successful, render normal data
	$body = wp_remote_retrieve_body( $response );
	$data = json_decode( $body, true );

	if ( ! empty( $data['plugins'] ) ) {
		echo '<div class="plugin-cards">';
		foreach ( $data['plugins'] as $plugin ) {
			echo '<div class="plugin-card" style="border:1px solid #ddd; padding:15px; margin:10px; border-radius:8px;">';
			echo '<h3>' . esc_html( $plugin['name'] ?? 'Unknown Plugin' ) . '</h3>';
			if ( ! empty( $plugin['version'] ) ) {
				echo '<p><strong>Version:</strong> ' . esc_html( $plugin['version'] ) . '</p>';
			}
			if ( ! empty( $plugin['short_description'] ) ) {
				echo '<p>' . esc_html( $plugin['short_description'] ) . '</p>';
			}
			echo '</div>';
		}
		echo '</div>';
	} else {
		echo '<p>No plugin data found.</p>';
	}
}
add_action( 'admin_notices', 'performance_lab_render_plugin_cards' );
