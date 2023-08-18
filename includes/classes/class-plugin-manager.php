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
		$standalone_plugins = self::get_standalone_plugins();
		?>
		<div class="wrap">
			<h1>Performance Plugins</h1>
			<p>The following standalone performance plugins are available for installation.</p>
			<div class="wrap">
				<div id="plugin-filter">
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
				</div>
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
		?>
		<div class="plugin-card plugin-card-<?php echo esc_attr( $standalone_plugin['Slug'] ); ?>" data-wpp-plugin="<?php echo esc_attr( $standalone_plugin['Slug'] ); ?>">
			<div class="plugin-card-top">
				<div class="name column-name">
					<h3>
						<a href="http://localhost:8888/wp-admin/plugin-install.php?tab=plugin-information&amp;plugin=<?php echo esc_attr( $standalone_plugin['Slug'] ); ?>&amp;TB_iframe=true&amp;width=600&amp;height=550" class="thickbox open-plugin-details-modal">
							<?php echo esc_html( $standalone_plugin['Name'] ); ?>
							<img src="https://s.w.org/plugins/geopattern-icon/<?php echo esc_attr( $standalone_plugin['Slug'] ); ?>.svg" class="plugin-icon" alt="">
						</a>
					</h3>
				</div>
				<div class="action-links">
					<ul class="plugin-action-buttons">
						<li>
							<?php
							switch ( $standalone_plugin['Status'] ) {
								case 'uninstalled':
									?>
									<button type="button" class="button">Install</button>
									<?php
									break;
								case 'active':
									?>
									<button type="button" class="button button-disabled" disabled="disabled">Active</button>
									<?php
									break;
								case 'inactive':
									?>
									<button type="button" class="button">Activate</button>
									<?php
									break;
							}
							?>
						</li>

						<?php if ( 'inactive' === $standalone_plugin['Status'] ) { ?>
							<li>
								<button type="button" class="button" style="display: inline; padding: 0; background: none; border: none; color: #DC3232; text-decoration: underline; display: block; text-align: center;">
									Uninstall
								</button>
							</li>
						<?php }; ?>

						<?php if ( 'active' === $standalone_plugin['Status'] ) { ?>
						<li>
							<button type="button" class="button" style="display: inline; padding: 0; background: none; border: none; color: #DC3232; text-decoration: underline; display: block; text-align: center;">
								Deactivate
							</button>
						</li>
						<?php }; ?>

					</ul>
				</div>
				<div class="desc column-description" style='min-height: 100px;'>
					<p><?php echo esc_html( $standalone_plugin['Description'] ); ?></p>
					<p class="authors">
						<cite>By <a href="<?php echo esc_attr( $standalone_plugin['AuthorURI'] ); ?>" target='_blank'><?php echo esc_attr( $standalone_plugin['Author'] ); ?></a></cite>
					</p>
				</div>
			</div>

		</div>
		<?php
	}
}
