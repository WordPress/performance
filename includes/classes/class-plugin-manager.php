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
 * General purpose class for managing installation/activation of standalone performance plugins
 * and rendering of the UI associated therewith.
 *
 * @since n.e.x.t
 * @access private
 * @ignore
 */
class Plugin_Manager {

	/**
	 * Get info about all the standalone plugins and their status.
	 *
	 * @return array of plugins info.
	 */
	public static function get_standalone_plugins() {
		$managed_standalone_plugins = array(
			'webp-uploads'                => array(
				'Name'        => esc_html__( 'WebP Uploads', 'performance-lab' ),
				'Description' => esc_html__( 'This plugin adds WebP support for media uploads within the WordPress application.', 'performance-lab' ),
				'Author'      => esc_html__( 'WordPress Performance Team', 'performance-lab' ),
				'AuthorURI'   => esc_url( 'https://make.wordpress.org/performance/' ),
				'PluginURI'   => esc_url( 'https://wordpress.org/plugins/webp-uploads/' ),
				'Download'    => 'wporg',
			),
			'sqlite-database-integration' => array(
				'Name'        => esc_html__( 'SQLite Database Integration', 'performance-lab' ),
				'Description' => esc_html__( 'Allows testing an SQLite integration with WordPress and gather feedback, with the goal of eventually landing it in WordPress core.', 'performance-lab' ),
				'Author'      => esc_html__( 'WordPress Performance Team', 'performance-lab' ),
				'AuthorURI'   => esc_url( 'https://make.wordpress.org/performance/' ),
				'PluginURI'   => esc_url( 'https://wordpress.org/plugins/sqlite-database-integration/' ),
				'Download'    => 'wporg',
			),
		);

		$default_info = array(
			'Name'        => '',
			'Description' => '',
			'Author'      => '',
			'Version'     => '',
			'PluginURI'   => '',
			'AuthorURI'   => '',
			'TextDomain'  => '',
			'DomainPath'  => '',
			'Download'    => '',
			'Status'      => '',
		);

		// Add plugin status info and fill in defaults.
		foreach ( $managed_standalone_plugins as $plugin_slug => $managed_standalone_plugin ) {
			$status = self::get_managed_standalone_plugin_status( $plugin_slug );

			$managed_standalone_plugins[ $plugin_slug ]['Status']      = $status;
			$managed_standalone_plugins[ $plugin_slug ]['Slug']        = $plugin_slug;
			$managed_standalone_plugins[ $plugin_slug ]['HandoffLink'] = isset( $managed_standalone_plugins[ $plugin_slug ]['EditPath'] ) ? admin_url( $managed_standalone_plugins[ $plugin_slug ]['EditPath'] ) : null;
			$managed_standalone_plugins[ $plugin_slug ]                = wp_parse_args( $managed_standalone_plugins[ $plugin_slug ], $default_info );
		}
		return $managed_standalone_plugins;
	}

	/**
	 * Determine a managed standalone plugin status.
	 *
	 * @param string $plugin_slug Plugin slug.
	 */
	private static function get_managed_standalone_plugin_status( $plugin_slug ) {
		$status            = 'uninstalled';
		$installed_plugins = self::get_installed_plugins();

		if ( isset( $installed_plugins[ $plugin_slug ] ) ) {
			if ( is_plugin_active( $installed_plugins[ $plugin_slug ] ) ) {
				$status = 'active';
			} else {
				$status = 'inactive';
			}
		}

		return $status;
	}


	/**
	 * Determine whether plugin installation is allowed in the current environment.
	 *
	 * @return bool
	 */
	public static function can_install_plugins() {
		if ( ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) ||
			( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Activate a plugin, installing it first if necessary.
	 *
	 * @param string $plugin The plugin slug or URL to the plugin.
	 * @return bool True on success. False on failure or if plugin was already activated.
	 */
	public static function activate( $plugin ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_slug = self::get_plugin_slug( $plugin );
		if ( ! $plugin_slug ) {
			return new WP_Error( 'wpp_invalid_plugin', __( 'Invalid plugin.', 'performance-lab' ) );
		}

		$installed_plugins = self::get_installed_plugins();

		// Install the plugin if it's not installed already.
		$plugin_installed = isset( $installed_plugins[ $plugin_slug ] );
		if ( ! $plugin_installed ) {
			$plugin_installed = self::install( $plugin );
		}

		// @phpstan-ignore-next-line false flag that this will always amount to true.
		if ( is_wp_error( $plugin_installed ) ) {
			return $plugin_installed;
		}

		// Refresh the installed plugin list if the plugin isn't present because we just installed it.
		if ( ! isset( $installed_plugins[ $plugin_slug ] ) ) {
			$installed_plugins = self::get_installed_plugins();
		}

		if ( is_plugin_active( $installed_plugins[ $plugin_slug ] ) ) {
			return new WP_Error( 'wpp_plugin_already_active', __( 'The plugin is already active.', 'performance-lab' ) );
		}

		$activated = activate_plugin( $installed_plugins[ $plugin_slug ] );
		if ( is_wp_error( $activated ) ) {
			return new WP_Error( 'wpp_plugin_failed_activation', $activated->get_error_message() );
		}

		return true;
	}

	/**
	 * Deactivate a plugin.
	 *
	 * @param string $plugin The plugin slug (e.g. 'newspack') or path to the plugin file. e.g. ('newspack/newspack.php').
	 * @return bool True on success. False on failure.
	 */
	public static function deactivate( $plugin ) {
		$installed_plugins = self::get_installed_plugins();
		if ( ! in_array( $plugin, $installed_plugins, true ) && ! isset( $installed_plugins[ $plugin ] ) ) {
			return new WP_Error( 'wpp_plugin_not_installed', __( 'The plugin is not installed.', 'performance-lab' ) );
		}

		if ( isset( $installed_plugins[ $plugin ] ) ) {
			$plugin_file = $installed_plugins[ $plugin ];
		} else {
			$plugin_file = $plugin;
		}

		if ( ! is_plugin_active( $plugin_file ) ) {
			return new WP_Error( 'wpp_plugin_not_active', __( 'The plugin is not active.', 'performance-lab' ) );
		}

		deactivate_plugins( $plugin_file );
		if ( is_plugin_active( $plugin_file ) ) {
			return new WP_Error( 'wpp_plugin_failed_deactivation', __( 'Failed to deactivate plugin.', 'performance-lab' ) );
		}
		return true;
	}

	/**
	 * Get a simple list of all installed plugins.
	 *
	 * @return array of 'plugin_slug => plugin_file_path' entries for all installed plugins.
	 */
	public static function get_installed_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = array_reduce( array_keys( get_plugins() ), array( __CLASS__, 'reduce_plugin_info' ) );
		$themes  = array_reduce( array_keys( wp_get_themes() ), array( __CLASS__, 'reduce_plugin_info' ) );
		return array_merge( $plugins, $themes );
	}

	/**
	 * Get a list of all installed plugins, with complete info for each.
	 *
	 * @return array of 'plugin_slug => []' entries for all installed plugins.
	 */
	public static function get_installed_plugins_info() {
		$plugins = array_merge( get_plugins(), wp_get_themes() );

		$installed_plugins_info = array();
		foreach ( self::get_installed_plugins() as $key => $path ) {
			$installed_plugins_info[ $key ]         = $plugins[ $path ];
			$installed_plugins_info[ $key ]['Path'] = $path;
		}
		return $installed_plugins_info;
	}

	/**
	 * Parse a plugin slug from the slug or URL to download a plugin.
	 *
	 * @param string $plugin A plugin slug or the URL to a plugin zip file.
	 * @return string|bool Parsed slug on success. False on failure.
	 */
	public static function get_plugin_slug( $plugin ) {
		if ( ! is_string( $plugin ) || empty( $plugin ) ) {
			return false;
		}

		$url = wp_http_validate_url( $plugin );

		// A plugin slug was passed in, so just return it.
		if ( ! $url ) {
			return $plugin;
		}

		if ( ! stripos( $url, '.zip' ) ) {
			return false;
		}

		$result = preg_match_all( '/\/([^\.\/*]+)/', $url, $matches );
		if ( ! $result ) {
			return false;
		}

		$group = end( $matches );
		$slug  = end( $group );
		return $slug;
	}

	/**
	 * Installs a plugin.
	 *
	 * @param string $plugin Plugin slug or URL to plugin zip file.
	 * @return bool True on success. False on failure.
	 */
	public static function install( $plugin ) {
		if ( ! self::can_install_plugins() ) {
			return new WP_Error( 'wpp_plugin_failed_install', __( 'Plugins cannot be installed.', 'performance-lab' ) );
		}

		if ( wp_http_validate_url( $plugin ) ) {
			return self::install_from_url( $plugin );
		} else {
			return self::install_from_slug( $plugin );
		}
	}

	/**
	 * Uninstall a plugin.
	 *
	 * @param string|array $plugin The plugin slug (e.g. 'webp-uploads') or path to the plugin file. e.g. ('webp-uploads/webp-uploads.php'), or an array thereof.
	 * @return bool True on success. False on failure.
	 */
	public static function uninstall( $plugin ) {
		if ( ! self::can_install_plugins() ) {
			return new WP_Error( 'wpp_plugin_failed_uninstall', __( 'Plugins cannot be uninstalled.', 'performance-lab' ) );
		}

		$plugins_to_uninstall = array();
		$installed_plugins    = self::get_installed_plugins();

		if ( ! is_array( $plugin ) ) {
			$plugin = array( $plugin );
		}

		foreach ( $plugin as $plugin_slug ) {
			if ( ! in_array( $plugin_slug, $installed_plugins, true ) && ! isset( $installed_plugins[ $plugin_slug ] ) ) {
				return new WP_Error( 'wpp_plugin_failed_uninstall', __( 'The plugin is not installed.', 'performance-lab' ) );
			}

			if ( isset( $installed_plugins[ $plugin_slug ] ) ) {
				$plugin_file = $installed_plugins[ $plugin_slug ];
			} else {
				$plugin_file = $plugin_slug;
			}

			// Deactivate plugin before uninstalling.
			self::deactivate( $plugin_file );

			$plugins_to_uninstall[] = $plugin_file;
		}

		if ( ! function_exists( 'request_filesystem_credentials' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$success = (bool) delete_plugins( $plugins_to_uninstall );
		if ( $success ) {
			wp_clean_plugins_cache();
			return true;
		}
		return new WP_Error( 'wpp_plugin_failed_uninstall', __( 'The plugin could not be uninstalled.', 'performance-lab' ) );
	}

	/**
	 * Install a plugin by slug.
	 *
	 * @param string $plugin_slug The slug for the plugin.
	 * @return Mixed True on success. WP_Error on failure.
	 */
	protected static function install_from_slug( $plugin_slug ) {
		// Quick check to make sure plugin directory doesn't already exist.
		$plugin_directory = WP_PLUGIN_DIR . '/' . $plugin_slug;
		if ( is_dir( $plugin_directory ) ) {
			return new WP_Error( 'wpp_plugin_already_installed', __( 'The plugin directory already exists.', 'performance-lab' ) );
		}

		$managed_plugins = self::get_standalone_plugins();
		if ( ! isset( $managed_plugins[ $plugin_slug ] ) ) {
			return new WP_Error(
				'wpp_plugin_failed_install',
				__( 'Plugin not found.', 'performance-lab' )
			);
		}

		// Return a useful error if we are unable to get download info for the plugin.
		if ( empty( $managed_plugins[ $plugin_slug ]['Download'] ) ) {
			$error_message = __( 'Performance Lab cannot install this plugin. You will need to get it from the plugin\'s site and install it manually.', 'performance-lab' );
			if ( ! empty( $managed_plugins[ $plugin_slug ]['PluginURI'] ) ) {
				/* translators: %s: plugin URL */
				$error_message = sprintf( __( 'Performance Lab cannot install this plugin. You will need to get it from <a href="%s">the plugin\'s site</a> and install it manually.', 'performance-lab' ), esc_url( $managed_plugins[ $plugin_slug ]['PluginURI'] ) );
			}

			return new WP_Error(
				'wpp_plugin_failed_install',
				$error_message
			);
		}

		// If the plugin has a URL as its Download, install it from there.
		if ( wp_http_validate_url( $managed_plugins[ $plugin_slug ]['Download'] ) ) {
			return self::install_from_url( $managed_plugins[ $plugin_slug ]['Download'] );
		}

		if ( ! function_exists( 'plugins_api' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		}

		// Check WP.org for a download link, and install it from WP.org.
		$plugin_info = plugins_api(
			'plugin_information',
			array(
				'slug'   => $plugin_slug,
				'fields' => array(
					'short_description' => false,
					'sections'          => false,
					'requires'          => false,
					'rating'            => false,
					'ratings'           => false,
					'downloaded'        => false,
					'last_updated'      => false,
					'added'             => false,
					'tags'              => false,
					'compatibility'     => false,
					'homepage'          => false,
					'donate_link'       => false,
				),
			)
		);

		if ( is_wp_error( $plugin_info ) ) {
			return new WP_Error( 'wpp_plugin_failed_install', $plugin_info->get_error_message() );
		}

		return self::install_from_url( $plugin_info->download_link );
	}

	/**
	 * Install a plugin from an arbitrary URL.
	 *
	 * @param string $plugin_url The URL to the plugin zip file.
	 * @return bool True on success. False on failure.
	 */
	protected static function install_from_url( $plugin_url ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		WP_Filesystem();

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \WP_Upgrader( $skin );
		$upgrader->init();

		$download = $upgrader->download_package( $plugin_url );
		if ( is_wp_error( $download ) ) {
			return new WP_Error( 'wpp_plugin_failed_install', $download->get_error_message() );
		}

		// GitHub appends random strings to the end of its downloads.
		// If we asked for foo.zip, make sure the downloaded file is called foo.tmp.
		if ( stripos( $plugin_url, 'github' ) ) {
			$plugin_url_parts  = explode( '/', $plugin_url );
			$desired_file_name = str_replace( '.zip', '', end( $plugin_url_parts ) );
			$new_file_name     = preg_replace( '#(' . $desired_file_name . '.*).tmp#', $desired_file_name . '.tmp', $download );
			rename( $download, $new_file_name ); // phpcs:ignore
			$download = $new_file_name;
		}

		$working_dir = $upgrader->unpack_package( $download );
		if ( is_wp_error( $working_dir ) ) {
			return new WP_Error( 'wpp_plugin_failed_install', $working_dir->get_error_message() );
		}

		$result = $upgrader->install_package(
			array(
				'source'        => $working_dir,
				'destination'   => WP_PLUGIN_DIR,
				'clear_working' => true,
				'hook_extra'    => array(
					'type'   => 'plugin',
					'action' => 'install',
				),
			)
		);
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'wpp_plugin_failed_install', $result->get_error_message() );
		}

		wp_clean_plugins_cache();
		return true;
	}

	/**
	 * Reduce get_plugins() info to form 'folder => file'.
	 *
	 * @param array  $plugins Associative array of plugin files to paths.
	 * @param string $key     Plugin relative path. Example: performance/performance.php.
	 * @return array
	 */
	private static function reduce_plugin_info( $plugins, $key ) {
		$path   = explode( '/', $key );
		$folder = current( $path );

		// Strip version info from key. (e.g. 'performance-lab-1.0.0' should just be 'performance-lab').
		$folder = preg_replace( '/[\-0-9\.]+$/', '', $folder );

		$plugins[ $folder ] = $key;
		return $plugins;
	}

	/**
	 * Renders plugin UI for managing standalone plugins.
	 *
	 * @return void
	 */
	public static function render_plugins_ui() {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$standalone_plugins = static::get_standalone_plugins();
		?>
		<div class="wrap">
			<h1>Performance Plugins</h1>
			<p>The following standalone performance plugins are available for installation.</p>
			<div class="wrap">
				<form id="plugin-filter" method="post">
					<div class="wp-list-table widefat plugin-install wpp-standalone-plugins">
						<h2 class="screen-reader-text">Plugins list</h2>
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
	 * @param array $standalone_plugin Array of plugin data passed from get_standalone_plugins().
	 *
	 * @return void
	 */
	private static function render_plugin_card( array $standalone_plugin = array() ) {
		$plugin = plugins_api(
			'plugin_information',
			array(
				'slug'   => $standalone_plugin['Slug'],
				'fields' => array(
					'short_description' => true,
					'icons'             => true,
				),
			)
		);

		if ( is_object( $plugin ) ) {
			$plugin = (array) $plugin;
		}

		$title = $plugin['name'];

		// Remove any HTML from the description.
		$description = wp_strip_all_tags( $plugin['short_description'] );

		/**
		 * Filters the plugin card description on the Add Plugins screen.
		 *
		 * @since 6.0.0
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
			$author = ' <cite>' . sprintf( __( 'By %s', 'performance-lab' ), $author ) . '</cite>';
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
								esc_attr( sprintf( _x( 'Install %s now', 'plugin', 'performance-lab' ), $name ) ),
								esc_attr( $name ),
								__( 'Install Now', 'performance-lab' )
							);
						} else {
							$action_links[] = sprintf(
								'<button type="button" class="button button-disabled" disabled="disabled">%s</button>',
								_x( 'Cannot Install', 'plugin', 'performance-lab' )
							);
						}
					}
					break;

				case 'update_available':
					if ( $status['url'] ) {
						if ( $compatible_php && $compatible_wp ) {
							$action_links[] = sprintf(
								'<a class="update-now button aria-button-if-js" data-plugin="%s" data-slug="%s" href="%s" aria-label="%s" data-name="%s">%s</a>',
								esc_attr( $status['file'] ),
								esc_attr( $plugin['slug'] ),
								esc_url( $status['url'] ),
								/* translators: %s: Plugin name and version. */
								esc_attr( sprintf( _x( 'Update %s now', 'plugin', 'performance-lab' ), $name ) ),
								esc_attr( $name ),
								__( 'Update Now', 'performance-lab' )
							);
						} else {
							$action_links[] = sprintf(
								'<button type="button" class="button button-disabled" disabled="disabled">%s</button>',
								_x( 'Cannot Update', 'plugin', 'performance-lab' )
							);
						}
					}
					break;

				case 'latest_installed':
				case 'newer_installed':
					if ( is_plugin_active( $status['file'] ) ) {
						$action_links[] = sprintf(
							'<button type="button" class="button button-disabled" disabled="disabled">%s</button>',
							_x( 'Active', 'plugin', 'performance-lab' )
						);
						if ( current_user_can( 'deactivate_plugin', $status['file'] ) ) {
							global $page, $paged;
							$s       = isset( $_REQUEST['s'] ) ? $_REQUEST['s'] : ''; // phpcs:ignore
							$context = $status['status'];

							$action_links[] = sprintf(
								'<a href="%s" id="deactivate-%s" aria-label="%s" style="color:red;text-decoration: underline;">%s</a>',
								wp_nonce_url( 'plugins.php?wpp=1&action=deactivate&amp;plugin=' . rawurlencode( $status['file'] ) . '&amp;plugin_status=' . $context . '&amp;paged=' . $page . '&amp;s=' . $s, 'deactivate-plugin_' . $status['file'] ),
								esc_attr( $plugin['slug'] ),
								/* translators: %s: Plugin name. */
								esc_attr( sprintf( _x( 'Deactivate %s', 'plugin', 'performance-lab' ), $plugin['slug'] ) ),
								__( 'Deactivate', 'performance-lab' )
							);
						}
					} elseif ( current_user_can( 'activate_plugin', $status['file'] ) ) {
						if ( $compatible_php && $compatible_wp ) {
							$button_text = __( 'Activate', 'performance-lab' );
							/* translators: %s: Plugin name. */
							$button_label = _x( 'Activate %s', 'plugin', 'performance-lab' );
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
								$button_text = __( 'Network Activate', 'performance-lab' );
								/* translators: %s: Plugin name. */
								$button_label = _x( 'Network Activate %s', 'plugin', 'performance-lab' );
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
								_x( 'Cannot Activate', 'plugin', 'performance-lab' )
							);
						}
					} else {
						$action_links[] = sprintf(
							'<button type="button" class="button button-disabled" disabled="disabled">%s</button>',
							_x( 'Installed', 'plugin', 'performance-lab' )
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
			esc_attr( sprintf( __( 'More information about %s', 'performance-lab' ), $name ) ),
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
		 * @since 2.7.0
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
							printf(
							/* translators: 1: URL to WordPress Updates screen, 2: URL to Update PHP page. */
								' ' . __( '<a href="%1$s">Please update WordPress</a>, and then <a href="%2$s">learn more about updating PHP</a>.', 'default' ), // phpcs:ignore
								esc_url( self_admin_url( 'update-core.php' ) ),
								esc_url( wp_get_update_php_url() )
							);
							wp_update_php_annotation( '</p><p><em>', '</em>' );
						} elseif ( current_user_can( 'update_core' ) ) {
							printf(
							/* translators: %s: URL to WordPress Updates screen. */
								' ' . __( '<a href="%s">Please update WordPress</a>.', 'performance-lab' ), // phpcs:ignore
								esc_url( self_admin_url( 'update-core.php' ) )
							);
						} elseif ( current_user_can( 'update_php' ) ) {
							printf(
							/* translators: %s: URL to Update PHP page. */
								' ' . __( '<a href="%s">Learn more about updating PHP</a>.', 'performance-lab' ), // phpcs:ignore
								esc_url( wp_get_update_php_url() )
							);
							wp_update_php_annotation( '</p><p><em>', '</em>' );
						}
					} elseif ( ! $compatible_wp ) {
						esc_html_e( 'This plugin does not work with your version of WordPress.', 'default' );
						if ( current_user_can( 'update_core' ) ) {
							printf(
							/* translators: %s: URL to WordPress Updates screen. */
								' ' . __( '<a href="%s">Please update WordPress</a>.', 'performance-lab' ), // phpcs:ignore
								esc_url( self_admin_url( 'update-core.php' ) )
							);
						}
					} elseif ( ! $compatible_php ) {
						esc_html_e( 'This plugin does not work with your version of PHP.', 'default' );
						if ( current_user_can( 'update_php' ) ) {
							printf(
							/* translators: %s: URL to Update PHP page. */
								' ' . __( '<a href="%s">Learn more about updating PHP</a>.', 'default' ), // phpcs:ignore
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
						if ( $action_links ) {
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
							$active_installs_text = _x( 'Less Than 10', 'Active plugin installations', 'performance-lab' );
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
