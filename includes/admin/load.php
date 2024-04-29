<?php
/**
 * Admin integration file.
 *
 * @package performance-lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Adds the features page to the Settings menu.
 *
 * @since 1.0.0
 * @since 3.0.0 Renamed to perflab_add_features_page().
 */
function perflab_add_features_page() {
	$hook_suffix = add_options_page(
		__( 'Performance Features', 'performance-lab' ),
		__( 'Performance', 'performance-lab' ),
		'manage_options',
		PERFLAB_SCREEN,
		'perflab_render_settings_page'
	);

	// Add the following hooks only if the screen was successfully added.
	if ( false !== $hook_suffix ) {
		add_action( "load-{$hook_suffix}", 'perflab_load_features_page', 10, 0 );
		add_filter( 'plugin_action_links_' . plugin_basename( PERFLAB_MAIN_FILE ), 'perflab_plugin_action_links_add_settings' );
	}

	return $hook_suffix;
}
add_action( 'admin_menu', 'perflab_add_features_page' );

/**
 * Initializes functionality for the features page.
 *
 * @since 1.0.0
 * @since 3.0.0 Renamed to perflab_load_features_page(), and the
 *              $module and $hook_suffix parameters were removed.
 */
function perflab_load_features_page() {
	// Handle script enqueuing for settings page.
	add_action( 'admin_enqueue_scripts', 'perflab_enqueue_features_page_scripts' );

	// Handle admin notices for settings page.
	add_action( 'admin_notices', 'perflab_plugin_admin_notices' );

	// Handle style for settings page.
	add_action( 'admin_head', 'perflab_print_features_page_style' );
}

/**
 * Renders the plugin page.
 *
 * @since 1.0.0
 * @since 3.0.0 Renamed to perflab_render_settings_page().
 */
function perflab_render_settings_page() {
	?>
	<div class="wrap">
		<?php perflab_render_plugins_ui(); ?>
	</div>
	<?php
}

/**
 * Initializes admin pointer.
 *
 * Handles the bootstrapping of the admin pointer.
 * Mainly jQuery code that is self-initialising.
 *
 * @since 1.0.0
 *
 * @param string $hook_suffix The current admin page.
 */
function perflab_admin_pointer( $hook_suffix ) {
	// Do not show admin pointer in multisite Network admin or User admin UI.
	if ( is_network_admin() || is_user_admin() ) {
		return;
	}
	$current_user = get_current_user_id();
	$dismissed    = array_filter( explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) ) );

	if ( in_array( 'perflab-admin-pointer', $dismissed, true ) ) {
		return;
	}

	if ( ! in_array( $hook_suffix, array( 'index.php', 'plugins.php' ), true ) ) {

		// Do not show on the settings page and dismiss the pointer.
		if ( isset( $_GET['page'] ) && PERFLAB_SCREEN === $_GET['page'] && ( ! in_array( 'perflab-admin-pointer', $dismissed, true ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$dismissed[] = 'perflab-admin-pointer';
			update_user_meta( $current_user, 'dismissed_wp_pointers', implode( ',', $dismissed ) );
		}

		return;
	}

	// Enqueue pointer CSS and JS.
	wp_enqueue_style( 'wp-pointer' );
	wp_enqueue_script( 'wp-pointer' );
	add_action( 'admin_print_footer_scripts', 'perflab_render_pointer', 10, 0 );
}
add_action( 'admin_enqueue_scripts', 'perflab_admin_pointer' );

/**
 * Renders the Admin Pointer.
 *
 * Handles the rendering of the admin pointer.
 *
 * @since 1.0.0
 * @since 2.4.0 Optional arguments were added to make the function reusable for different pointers.
 *
 * @param string $pointer_id Optional. ID of the pointer. Default 'perflab-admin-pointer'.
 * @param array  $args       Optional. Pointer arguments. Supports 'heading' and 'content' entries.
 *                           Defaults are the heading and content for the 'perflab-admin-pointer'.
 */
function perflab_render_pointer( $pointer_id = 'perflab-admin-pointer', $args = array() ) {
	if ( ! isset( $args['heading'] ) ) {
		$args['heading'] = __( 'Performance Lab', 'performance-lab' );
	}
	if ( ! isset( $args['content'] ) ) {
		$args['content'] = sprintf(
			/* translators: %s: settings page link */
			esc_html__( 'You can now test upcoming WordPress performance features. Open %s to individually toggle the performance features.', 'performance-lab' ),
			'<a href="' . esc_url( add_query_arg( 'page', PERFLAB_SCREEN, admin_url( 'options-general.php' ) ) ) . '">' . esc_html__( 'Settings > Performance', 'performance-lab' ) . '</a>'
		);
	}

	$wp_kses_options = array(
		'a' => array(
			'href' => array(),
		),
	);

	?>
	<script id="<?php echo esc_attr( $pointer_id ); ?>" type="text/javascript">
		jQuery( function() {
			// Pointer Options.
			const options = {
				content: <?php echo wp_json_encode( '<h3>' . esc_html( $args['heading'] ) . '</h3><p>' . wp_kses( $args['content'], $wp_kses_options ) . '</p>' ); ?>,
				position: {
					edge:  'left',
					align: 'right',
				},
				pointerClass: 'wp-pointer arrow-top',
				pointerWidth: 420,
				close: function() {
					jQuery.post(
						window.ajaxurl,
						{
							pointer: <?php echo wp_json_encode( $pointer_id ); ?>,
							action:  'dismiss-wp-pointer',
							_wpnonce: <?php echo wp_json_encode( wp_create_nonce( 'dismiss_pointer' ) ); ?>,
						}
					);
				}
			};

			jQuery( '#menu-settings' ).pointer( options ).pointer( 'open' );
		} );
	</script>
	<?php
}

/**
 * Adds a link to the features page to the plugin's entry in the plugins list table.
 *
 * This function is only used if the features page exists and is accessible.
 *
 * @since 1.0.0
 *
 * @see perflab_add_features_page()
 *
 * @param string[]|mixed $links List of plugin action links HTML.
 * @return string[]|mixed Modified list of plugin action links HTML.
 */
function perflab_plugin_action_links_add_settings( $links ) {
	if ( ! is_array( $links ) ) {
		return $links;
	}

	// Add link as the first plugin action link.
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( add_query_arg( 'page', PERFLAB_SCREEN, admin_url( 'options-general.php' ) ) ),
		esc_html__( 'Settings', 'performance-lab' )
	);

	return array_merge(
		array( 'settings' => $settings_link ),
		$links
	);
}

/**
 * Dismisses notification pointer after verifying nonce.
 *
 * This function adds a nonce check before dismissing perflab-admin-pointer
 * It runs before the dismiss-wp-pointer AJAX action is performed.
 *
 * @since 2.3.0
 */
function perflab_dismiss_wp_pointer_wrapper() {
	if ( isset( $_POST['pointer'] ) && 'perflab-admin-pointer' !== $_POST['pointer'] ) {
		// Another plugin's pointer, do nothing.
		return;
	}
	check_ajax_referer( 'dismiss_pointer' );
}
add_action( 'wp_ajax_dismiss-wp-pointer', 'perflab_dismiss_wp_pointer_wrapper', 0 );

/**
 * Callback function to handle admin scripts.
 *
 * @since 2.8.0
 * @since 3.0.0 Renamed to perflab_enqueue_features_page_scripts().
 */
function perflab_enqueue_features_page_scripts() {
	// These assets are needed for the "Learn more" popover.
	wp_enqueue_script( 'thickbox' );
	wp_enqueue_style( 'thickbox' );
	wp_enqueue_script( 'plugin-install' );
}

/**
 * Callback for handling installation/activation of plugin.
 *
 * @since 3.0.0
 */
function perflab_install_activate_plugin_callback() {
	check_admin_referer( 'perflab_install_activate_plugin' );

	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';

	if ( ! isset( $_GET['slug'] ) ) {
		wp_die( esc_html__( 'Missing required parameter.', 'performance-lab' ) );
	}

	$plugin_slug = sanitize_text_field( wp_unslash( $_GET['slug'] ) );
	if ( ! in_array( $plugin_slug, perflab_get_standalone_plugins(), true ) ) {
		wp_die( esc_html__( 'Invalid plugin.', 'performance-lab' ) );
	}

	// Install and activate the plugin and its dependencies.
	$result = perflab_install_and_activate_plugin( $plugin_slug );
	if ( $result instanceof WP_Error ) {
		wp_die( wp_kses_post( $result->get_error_message() ) );
	}

	$url = add_query_arg(
		array(
			'page'     => PERFLAB_SCREEN,
			'activate' => 'true',
		),
		admin_url( 'options-general.php' )
	);

	if ( wp_safe_redirect( $url ) ) {
		exit;
	}
}
add_action( 'admin_action_perflab_install_activate_plugin', 'perflab_install_activate_plugin_callback' );

/**
 * Callback function to handle admin inline style.
 *
 * @since 3.0.0
 */
function perflab_print_features_page_style() {
	?>
<style type="text/css">
	.plugin-card .name,
	.plugin-card .desc, /* For WP <6.5 versions */
	.plugin-card .desc > p {
		margin-left: 0;
	}
	.plugin-card-top {
		min-height: auto;
	}
	.plugin-card .perflab-plugin-experimental {
		font-size: 80%;
		font-weight: normal;
	}
</style>
	<?php
}

/**
 * Callback function hooked to admin_notices to render admin notices on the plugin's screen.
 *
 * @since 2.8.0
 */
function perflab_plugin_admin_notices() {
	if ( ! current_user_can( 'install_plugins' ) ) {
		$are_all_plugins_installed = true;
		$installed_plugin_slugs    = array_map(
			static function ( $name ) {
				return strtok( $name, '/' );
			},
			array_keys( get_plugins() )
		);
		foreach ( perflab_get_standalone_plugin_version_constants() as $plugin_slug => $constant_name ) {
			if ( ! in_array( $plugin_slug, $installed_plugin_slugs, true ) ) {
				$are_all_plugins_installed = false;
				break;
			}
		}

		if ( ! $are_all_plugins_installed ) {
			wp_admin_notice(
				esc_html__( 'Due to your site\'s configuration, you may not be able to activate the performance features, unless the underlying plugin is already installed. Please install the relevant plugins manually.', 'performance-lab' ),
				array(
					'type' => 'warning',
				)
			);
			return;
		}
	}

	if ( isset( $_GET['activate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		wp_admin_notice(
			esc_html__( 'Feature activated.', 'performance-lab' ),
			array(
				'type'        => 'success',
				'dismissible' => true,
			)
		);
	}
}
