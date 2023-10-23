<?php
/**
 * Class PerformanceLab\Plugin_Manager
 *
 * @package PerformanceLab
 * Author: WordPress Performance Team
 * Author URI: https://make.wordpress.org/performance/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

namespace PerformanceLab;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * General purpose class for managing the UI of standalone performance plugins
 * and rendering of the UI associated therewith.
 *
 * @since n.e.x.t
 * @access public
 * @ignore
 */
class Plugin_Manager {

	/**
	 * Get info about all the standalone plugins and their status.
	 *
	 * @since n.e.x.t
	 *
	 * @return array of plugins info.
	 */
	public static function get_standalone_plugins() {
		$managed_standalone_plugins = array(
			'webp-uploads',
			'performant-translations',
			'dominant-color-images',
		);

		return $managed_standalone_plugins;
	}

	/**
	 * Renders plugin UI for managing standalone plugins within PL Settings screen.
	 *
	 * @since n.e.x.t
	 *
	 * @return void
	 */
	public static function render_plugins_ui() {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$standalone_plugins = static::get_standalone_plugins();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Performance Plugins', 'performance-lab' ); ?></h1>
			<p><?php echo esc_html__( 'The following standalone performance plugins are available for installation.', 'performance-lab' ); ?></p>
			<div class="wrap">
				<form id="plugin-filter" method="post">
					<div class="wp-list-table widefat plugin-install wpp-standalone-plugins">
						<h2 class="screen-reader-text"><?php echo esc_html__( 'Plugins list', 'default' ); ?></h2>
						<div id="the-list">
							<?php
							foreach ( $standalone_plugins as $standalone_plugin ) {
								self::render_plugin_card( $standalone_plugin );
							}
							?>
						</div>
					</div>
				</form>
			</div>
			<div class="clear"></div>
		</div>
		<?php
	}

	/**
	 * Render individual plugin cards.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $standalone_plugin Plugin slug as passed from get_standalone_plugins().
	 *
	 * @return void
	 */
	private static function render_plugin_card( string $standalone_plugin ) {
		$plugin = plugins_api(
			'plugin_information',
			array(
				'slug'   => $standalone_plugin,
				'fields' => array(
					'short_description' => true,
					'icons'             => true,
				),
			)
		);
		if ( is_object( $plugin ) ) {
			$plugin = (array) $plugin;
		}

		// Remove any HTML from the description.
		$description = wp_strip_all_tags( $plugin['short_description'] );
		$title       = $plugin['name'];

		/**
		 * Filters the plugin card description on the Add Plugins screen.
		 *
		 * @since n.e.x.t
		 *
		 * @param string $description Plugin card description.
		 * @param array  $plugin      An array of plugin data. See {@see plugins_api()}
		 *                            for the list of possible values.
		 */
		$description = apply_filters( 'plugin_install_description', $description, $plugin );
		$version     = $plugin['version'];
		$name        = wp_strip_all_tags( $title . ' ' . $version );
		$author      = $plugin['author'];
		if ( ! empty( $author ) ) {
			/* translators: %s: Plugin author. */
			$author = ' <cite>' . sprintf( __( 'By %s', 'default' ), $author ) . '</cite>';
		}

		$requires_php = isset( $plugin['requires_php'] ) ? $plugin['requires_php'] : null;
		$requires_wp  = isset( $plugin['requires'] ) ? $plugin['requires'] : null;

		$compatible_php = is_php_version_compatible( $requires_php );
		$compatible_wp  = is_wp_version_compatible( $requires_wp );
		$tested_wp      = ( empty( $plugin['tested'] ) || version_compare( get_bloginfo( 'version' ), $plugin['tested'], '<=' ) );
		$action_links   = array();

		if ( current_user_can( 'install_plugins' ) || current_user_can( 'update_plugins' ) ) {
			$status = install_plugin_install_status( $plugin );

			switch ( $status['status'] ) {
				case 'install':
					if ( $status['url'] ) {
						if ( $compatible_php && $compatible_wp ) {
							$action_links[] = sprintf(
								'<a class="install-now button" data-slug="%s" href="%s" aria-label="%s" data-name="%s">%s</a>',
								esc_attr( $plugin['slug'] ),
								esc_url( $status['url'] ),
								/* translators: %s: Plugin name and version. */
								esc_attr( sprintf( _x( 'Install %s now', 'plugin', 'default' ), $name ) ),
								esc_attr( $name ),
								esc_html__( 'Install Now', 'default' )
							);
						} else {
							$action_links[] = sprintf(
								'<button type="button" class="button button-disabled" disabled="disabled">%s</button>',
								esc_html( _x( 'Cannot Install', 'plugin', 'default' ) )
							);
						}
					}
					break;

				case 'update_available':
					if ( $status['url'] ) {
						if ( $compatible_php && $compatible_wp ) {
							$action_links[] = sprintf(
								'<a class="button aria-button-if-js" data-plugin="%s" data-slug="%s" href="%s" aria-label="%s" data-name="%s">%s</a>',
								esc_attr( $status['file'] ),
								esc_attr( $plugin['slug'] ),
								esc_url( $status['url'] ),
								/* translators: %s: Plugin name and version. */
								esc_attr( sprintf( _x( 'Update %s now', 'plugin', 'default' ), $name ) ),
								esc_attr( $name ),
								esc_html__( 'Update Now', 'default' )
							);
						} else {
							$action_links[] = sprintf(
								'<button type="button" class="button button-disabled" disabled="disabled">%s</button>',
								esc_html( _x( 'Cannot Update', 'plugin', 'default' ) )
							);
						}
					}
					break;

				case 'latest_installed':
				case 'newer_installed':
					if ( is_plugin_active( $status['file'] ) ) {
						$action_links[] = sprintf(
							'<button type="button" class="button button-disabled" disabled="disabled">%s</button>',
							esc_html( _x( 'Active', 'plugin', 'default' ) )
						);
						if ( current_user_can( 'deactivate_plugin', $status['file'] ) ) {
							global $page;
							$s       = isset( $_REQUEST['s'] ) ? $_REQUEST['s'] : ''; // phpcs:ignore
							$context = $status['status'];

							$action_links[] = sprintf(
								'<a href="%s" id="deactivate-%s" aria-label="%s" style="color:red;text-decoration: underline;">%s</a>',
								wp_nonce_url( 'plugins.php?wpp=1&action=deactivate&amp;plugin=' . rawurlencode( $status['file'] ) . '&amp;plugin_status=' . $context . '&amp;paged=' . $page . '&amp;s=' . $s, 'deactivate-plugin_' . $status['file'] ),
								esc_attr( $plugin['slug'] ),
								/* translators: %s: Plugin name. */
								esc_attr( sprintf( _x( 'Deactivate %s', 'plugin', 'default' ), $plugin['slug'] ) ),
								__( 'Deactivate', 'default' )
							);
						}
					} elseif ( current_user_can( 'activate_plugin', $status['file'] ) ) {
						if ( $compatible_php && $compatible_wp ) {
							$button_text = __( 'Activate', 'default' );
							/* translators: %s: Plugin name. */
							$button_label = _x( 'Activate %s', 'plugin', 'default' );
							$activate_url = add_query_arg(
								array(
									'_wpnonce' => wp_create_nonce( 'activate-plugin_' . $status['file'] ),
									'action'   => 'activate',
									'plugin'   => $status['file'],
									'wpp'      => 1,
								),
								network_admin_url( 'plugins.php' )
							);

							if ( is_network_admin() ) {
								$button_text = __( 'Network Activate', 'default' );
								/* translators: %s: Plugin name. */
								$button_label = _x( 'Network Activate %s', 'plugin', 'default' );
								$activate_url = add_query_arg( array( 'networkwide' => 1 ), $activate_url );
							}

							$action_links[] = sprintf(
								'<a href="%1$s" class="button activate-now" aria-label="%2$s">%3$s</a>',
								esc_url( $activate_url ),
								esc_attr( sprintf( $button_label, $plugin['name'] ) ),
								$button_text
							);
						} else {
							$action_links[] = sprintf(
								'<button type="button" class="button button-disabled" disabled="disabled">%s</button>',
								_x( 'Cannot Activate', 'plugin', 'default' )
							);
						}
					} else {
						$action_links[] = sprintf(
							'<button type="button" class="button button-disabled" disabled="disabled">%s</button>',
							esc_html( _x( 'Installed', 'plugin', 'default' ) )
						);
					}
					break;
			}
		}

		$details_link = self_admin_url(
			'plugin-install.php?tab=plugin-information&amp;plugin=' . $plugin['slug'] .
			'&amp;TB_iframe=true&amp;width=600&amp;height=550'
		);

		$action_links[] = sprintf(
			'<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>',
			esc_url( $details_link ),
			/* translators: %s: Plugin name and version. */
			esc_attr( sprintf( __( 'More information about %s', 'default' ), $name ) ),
			esc_attr( $name ),
			__( 'More Details', 'performance-lab' )
		);

		if ( ! empty( $plugin['icons']['svg'] ) ) {
			$plugin_icon_url = $plugin['icons']['svg'];
		} elseif ( ! empty( $plugin['icons']['2x'] ) ) {
			$plugin_icon_url = $plugin['icons']['2x'];
		} elseif ( ! empty( $plugin['icons']['1x'] ) ) {
			$plugin_icon_url = $plugin['icons']['1x'];
		} else {
			$plugin_icon_url = $plugin['icons']['default'];
		}

		/**
		 * Filters the install action links for a plugin.
		 *
		 * @since n.e.x.t
		 *
		 * @param string[] $action_links An array of plugin action links.
		 *                               Defaults are links to Details and Install Now.
		 * @param array    $plugin       An array of plugin data. See {@see plugins_api()}
		 *                               for the list of possible values.
		 */
		$action_links = apply_filters( 'plugin_install_action_links', $action_links, $plugin );

		$last_updated_timestamp = strtotime( $plugin['last_updated'] );
		?>
			<div class="plugin-card plugin-card-<?php echo sanitize_html_class( $plugin['slug'] ); ?>">
				<?php
				if ( ! $compatible_php || ! $compatible_wp ) {
					echo '<div class="notice inline notice-error notice-alt"><p>';
					if ( ! $compatible_php && ! $compatible_wp ) {
						esc_html_e( 'This plugin does not work with your versions of WordPress and PHP.', 'default' );
						if ( current_user_can( 'update_core' ) && current_user_can( 'update_php' ) ) {
							echo wp_kses_post(
							/* translators: 1: URL to WordPress Updates screen, 2: URL to Update PHP page. */
								' ' . __( '<a href="%1$s">Please update WordPress</a>, and then <a href="%2$s">learn more about updating PHP</a>.', 'default' ),
								esc_url( self_admin_url( 'update-core.php' ) ),
								esc_url( wp_get_update_php_url() )
							);
							wp_update_php_annotation( '</p><p><em>', '</em>' );
						} elseif ( current_user_can( 'update_core' ) ) {
							echo wp_kses_post(
							/* translators: %s: URL to WordPress Updates screen. */
								' ' . __( '<a href="%s">Please update WordPress</a>.', 'default' ),
								esc_url( self_admin_url( 'update-core.php' ) )
							);
						} elseif ( current_user_can( 'update_php' ) ) {
							echo wp_kses_post(
							/* translators: %s: URL to Update PHP page. */
								' ' . __( '<a href="%s">Learn more about updating PHP</a>.', 'default' ),
								esc_url( wp_get_update_php_url() )
							);
							wp_update_php_annotation( '</p><p><em>', '</em>' );
						}
					} elseif ( ! $compatible_wp ) {
						esc_html_e( 'This plugin does not work with your version of WordPress.', 'default' );
						if ( current_user_can( 'update_core' ) ) {
							echo wp_kses_post(
							/* translators: %s: URL to WordPress Updates screen. */
								' ' . __( '<a href="%s">Please update WordPress</a>.', 'default' ),
								esc_url( self_admin_url( 'update-core.php' ) )
							);
						}
					} elseif ( ! $compatible_php ) {
						esc_html_e( 'This plugin does not work with your version of PHP.', 'default' );
						if ( current_user_can( 'update_php' ) ) {
							echo wp_kses_post(
							/* translators: %s: URL to Update PHP page. */
								' ' . __( '<a href="%s">Learn more about updating PHP</a>.', 'default' ),
								esc_url( wp_get_update_php_url() )
							);
							wp_update_php_annotation( '</p><p><em>', '</em>' );
						}
					}
					echo '</p></div>';
				}
				?>
				<div class="plugin-card-top">
					<div class="name column-name">
						<h3>
							<a href="<?php echo esc_url( $details_link ); ?>" class="thickbox open-plugin-details-modal">
								<?php echo wp_kses_post( $title ); ?>
								<img src="<?php echo esc_url( $plugin_icon_url ); ?>" class="plugin-icon" alt="" />
							</a>
						</h3>
					</div>
					<div class="action-links">
						<?php
						if ( ! empty( $action_links ) ) {
							echo wp_kses_post( '<ul class="plugin-action-buttons"><li>' . implode( '</li><li>', $action_links ) . '</li></ul>' );
						}
						?>
					</div>
					<div class="desc column-description">
						<p><?php echo wp_kses_post( $description ); ?></p>
						<p class="authors"><?php echo wp_kses_post( $author ); ?></p>
					</div>
				</div>
				<div class="plugin-card-bottom">
					<div class="vers column-rating">
						<?php
						wp_star_rating(
							array(
								'rating' => $plugin['rating'],
								'type'   => 'percent',
								'number' => $plugin['num_ratings'],
							)
						);
						?>
						<span class="num-ratings" aria-hidden="true">(<?php echo esc_html( number_format_i18n( $plugin['num_ratings'] ) ); ?>)</span>
					</div>
					<div class="column-updated">
						<strong><?php esc_html_e( 'Last Updated:', 'default' ); ?></strong>
						<?php
						/* translators: %s: Human-readable time difference. */
						printf( __( '%s ago', 'performance-lab' ), human_time_diff( $last_updated_timestamp ) ); // phpcs:ignore
						?>
					</div>
					<div class="column-downloaded">
						<?php
						if ( $plugin['active_installs'] >= 1000000 ) {
							$active_installs_millions = floor( $plugin['active_installs'] / 1000000 );
							$active_installs_text     = sprintf(
							/* translators: %s: Number of millions. */
								_nx( '%s+ Million', '%s+ Million', $active_installs_millions, 'Active plugin installations', 'default' ),
								number_format_i18n( $active_installs_millions )
							);
						} elseif ( 0 === $plugin['active_installs'] ) {
							$active_installs_text = _x( 'Less Than 10', 'Active plugin installations', 'default' );
						} else {
							$active_installs_text = number_format_i18n( $plugin['active_installs'] ) . '+';
						}
						/* translators: %s: Number of installations. */
						printf( esc_html__( '%s Active Installations', 'default' ), esc_html( $active_installs_text ) );
						?>
					</div>
					<div class="column-compatibility">
						<?php
						if ( ! $tested_wp ) {
							echo '<span class="compatibility-untested">' . esc_html__( 'Untested with your version of WordPress', 'default' ) . '</span>';
						} elseif ( ! $compatible_wp ) {
							echo '<span class="compatibility-incompatible">' . wp_kses_post( __( '<strong>Incompatible</strong> with your version of WordPress', 'default' ) ) . '</span>';
						} else {
							echo '<span class="compatibility-compatible">' . wp_kses_post( __( '<strong>Compatible</strong> with your version of WordPress', 'default' ) ) . '</span>';
						}
						?>
					</div>
				</div>
			</div>
		<?php
	}
}
