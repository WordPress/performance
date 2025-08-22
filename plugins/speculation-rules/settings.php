<?php
/**
 * Settings functions used for Speculative Loading.
 *
 * @package speculation-rules
 * @since 1.0.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Returns the available options for the Speculative Loading mode and their labels.
 *
 * @since 1.0.0
 *
 * @return array{ prefetch: string, prerender: string } Associative array of `$mode => $label` pairs.
 */
function plsr_get_mode_labels(): array {
	return array(
		'prefetch'  => _x( 'Prefetch', 'setting label', 'speculation-rules' ),
		'prerender' => _x( 'Prerender', 'setting label', 'speculation-rules' ),
	);
}

/**
 * Returns the available options for the Speculative Loading eagerness and their labels.
 *
 * @since 1.0.0
 *
 * @return array{ conservative: string, moderate: string, eager: string } Associative array of `$eagerness => $label` pairs.
 */
function plsr_get_eagerness_labels(): array {
	return array(
		'conservative' => _x( 'Conservative (typically on click)', 'setting label', 'speculation-rules' ),
		'moderate'     => _x( 'Moderate (typically on hover)', 'setting label', 'speculation-rules' ),
		'eager'        => _x( 'Eager (on slightest suggestion)', 'setting label', 'speculation-rules' ),
	);
}

/**
 * Returns the available options for the Speculative Loading authentication and their labels.
 *
 * @since n.e.x.t
 *
 * @return array{ logged_out: string, logged_out_and_admins: string, any: string } Associative array of `$authentication => $label` pairs.
 */
function plsr_get_authentication_labels(): array {
	return array(
		'logged_out'            => _x( 'Logged-out visitors only (default)', 'setting label', 'speculation-rules' ),
		'logged_out_and_admins' => _x( 'Administrators and logged-out visitors', 'setting label', 'speculation-rules' ),
		'any'                   => _x( 'Any user (logged-in or logged-out)', 'setting label', 'speculation-rules' ),
	);
}

/**
 * Returns translated description strings for settings fields.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param string $field The field name to get description for.
 * @param bool   $strip_tags Whether to strip HTML tags from the description.
 * @return string The translated description string.
 */
function plsr_get_field_description( string $field, bool $strip_tags = false ): string {
	$descriptions = array(
		'mode'           => __( 'Prerendering will lead to faster load times than prefetching. However, in case of interactive content, prefetching may be a safer choice.', 'speculation-rules' ),
		'eagerness'      => __( 'The eagerness setting defines the heuristics based on which the loading is triggered. "Eager" will have the minimum delay to start speculative loads, "Conservative" increases the chance that only URLs the user actually navigates to are loaded.', 'speculation-rules' ),
		'authentication' => sprintf(
			/* translators: %s: URL to persistent object cache documentation */
			__( 'Only unauthenticated pages are typically served from cache. So in order to reduce load on the server, speculative loading is not enabled by default for logged-in users. If your server can handle the additional load, you can opt in to speculative loading for all logged-in users or just administrator users only. For optimal performance when enabling authenticated user support, ensure you have a <a href="%s" target="_blank">persistent object cache</a> configured. This only applies to pages on frontend; admin screens remain excluded.', 'speculation-rules' ),
			'https://developer.wordpress.org/advanced-administration/performance/optimization/#object-caching'
		),
	);
	$description  = $descriptions[ $field ] ?? '';

	return $strip_tags ? wp_strip_all_tags( $description ) : $description;
}

/**
 * Returns the default setting value for Speculative Loading configuration.
 *
 * @since 1.0.0
 *
 * @return array{ mode: 'prerender', eagerness: 'moderate', authentication: 'logged_out' } {
 *     Default setting value.
 *
 *     @type string $mode           Mode.
 *     @type string $eagerness      Eagerness.
 *     @type string $authentication Authentication.
 * }
 */
function plsr_get_setting_default(): array {
	return array(
		'mode'           => 'prerender',
		'eagerness'      => 'moderate',
		'authentication' => 'logged_out',
	);
}

/**
 * Returns the stored setting value for Speculative Loading configuration.
 *
 * @since 1.4.0
 *
 * @return array{ mode: 'prefetch'|'prerender', eagerness: 'conservative'|'moderate'|'eager', authentication: 'logged_out'|'logged_out_and_admins'|'any' } {
 *     Stored setting value.
 *
 *     @type string $mode           Mode.
 *     @type string $eagerness      Eagerness.
 *     @type string $authentication Authentication.
 * }
 */
function plsr_get_stored_setting_value(): array {
	return plsr_sanitize_setting( get_option( 'plsr_speculation_rules' ) );
}

/**
 * Sanitizes the setting for Speculative Loading configuration.
 *
 * @since 1.0.0
 * @todo  Consider whether the JSON schema for the setting could be reused here.
 *
 * @param mixed $input Setting to sanitize.
 * @return array{ mode: 'prefetch'|'prerender', eagerness: 'conservative'|'moderate'|'eager', authentication: 'logged_out'|'logged_out_and_admins'|'any' } {
 *     Sanitized setting.
 *
 *     @type string $mode           Mode.
 *     @type string $eagerness      Eagerness.
 *     @type string $authentication Authentication.
 * }
 */
function plsr_sanitize_setting( $input ): array {
	$default_value = plsr_get_setting_default();

	if ( ! is_array( $input ) ) {
		return $default_value;
	}

	// Ensure only valid keys are present.
	$value = array_intersect_key( array_merge( $default_value, $input ), $default_value );

	// Constrain values to what is allowed.
	if ( ! in_array( $value['mode'], array_keys( plsr_get_mode_labels() ), true ) ) {
		$value['mode'] = $default_value['mode'];
	}
	if ( ! in_array( $value['eagerness'], array_keys( plsr_get_eagerness_labels() ), true ) ) {
		$value['eagerness'] = $default_value['eagerness'];
	}
	if ( ! in_array( $value['authentication'], array_keys( plsr_get_authentication_labels() ), true ) ) {
		$value['authentication'] = $default_value['authentication'];
	}

	return $value;
}

/**
 * Registers setting to control Speculative Loading configuration.
 *
 * @since 1.0.0
 * @access private
 */
function plsr_register_setting(): void {
	register_setting(
		'reading',
		'plsr_speculation_rules',
		array(
			'type'              => 'object',
			'description'       => __( 'Configuration for the Speculation Rules API.', 'speculation-rules' ),
			'sanitize_callback' => 'plsr_sanitize_setting',
			'default'           => plsr_get_setting_default(),
			'show_in_rest'      => array(
				'schema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'mode'           => array(
							'description' => plsr_get_field_description( 'mode', true ),
							'type'        => 'string',
							'enum'        => array_keys( plsr_get_mode_labels() ),
						),
						'eagerness'      => array(
							'description' => plsr_get_field_description( 'eagerness', true ),
							'type'        => 'string',
							'enum'        => array_keys( plsr_get_eagerness_labels() ),
						),
						'authentication' => array(
							'description' => plsr_get_field_description( 'authentication', true ),
							'type'        => 'string',
							'enum'        => array_keys( plsr_get_authentication_labels() ),
						),
					),
					'additionalProperties' => false,
				),
			),
		)
	);
}
add_action( 'init', 'plsr_register_setting' );

/**
 * Adds the settings sections and fields for the Speculative Loading configuration.
 *
 * @since 1.0.0
 * @access private
 */
function plsr_add_setting_ui(): void {
	add_settings_section(
		'plsr_speculation_rules',
		__( 'Speculative Loading', 'speculation-rules' ),
		static function (): void {
			?>
			<p class="description">
				<?php esc_html_e( 'This section allows you to control how URLs that your users navigate to are speculatively loaded to improve performance.', 'speculation-rules' ); ?>
			</p>
			<?php
		},
		'reading',
		array(
			'before_section' => '<div id="speculative-loading">',
			'after_section'  => '</div>',
		)
	);

	$fields = array(
		'mode'           => array(
			'title'       => __( 'Speculation Mode', 'speculation-rules' ),
			'description' => plsr_get_field_description( 'mode' ),
		),
		'eagerness'      => array(
			'title'       => __( 'Eagerness', 'speculation-rules' ),
			'description' => plsr_get_field_description( 'eagerness' ),
		),
		'authentication' => array(
			'title'       => __( 'User Authentication Status', 'speculation-rules' ),
			'description' => plsr_get_field_description( 'authentication' ),
		),
	);
	foreach ( $fields as $slug => $args ) {
		add_settings_field(
			"plsr_speculation_rules_{$slug}",
			$args['title'],
			'plsr_render_settings_field',
			'reading',
			'plsr_speculation_rules',
			array_merge(
				array( 'field' => $slug ),
				$args
			)
		);
	}
}
add_action( 'load-options-reading.php', 'plsr_add_setting_ui' );

/**
 * Renders a settings field for the Speculative Loading configuration.
 *
 * @since 1.0.0
 * @access private
 *
 * @param array{ field: 'mode'|'eagerness'|'authentication', title: non-empty-string, description: non-empty-string } $args {
 *     Associative array of arguments.
 *
 *     @type string $field       The slug of the sub setting controlled by the field.
 *     @type string $title       The title for the field.
 *     @type string $description Optional. A description to show for the field.
 * }
 */
function plsr_render_settings_field( array $args ): void {
	$option = plsr_get_stored_setting_value();

	switch ( $args['field'] ) {
		case 'mode':
			$choices = plsr_get_mode_labels();
			break;
		case 'eagerness':
			$choices = plsr_get_eagerness_labels();
			break;
		case 'authentication':
			$choices = plsr_get_authentication_labels();
			break;
		default:
			// Invalid (and this case should never occur).
			return; // @codeCoverageIgnore
	}

	$value                 = $option[ $args['field'] ];
	$authenticated_options = array( 'logged_out_and_admins', 'any' );
	$show_notice           = 'authentication' === $args['field'] && ! wp_using_ext_object_cache();
	$show_warning          = $show_notice && in_array( $value, $authenticated_options, true );
	?>
	<fieldset>
		<legend class="screen-reader-text"><?php echo esc_html( $args['title'] ); ?></legend>
		<?php foreach ( $choices as $slug => $label ) : ?>
			<p>
				<label>
					<input
						<?php echo $show_notice ? 'class="plsr-auth-radio"' : ''; ?>
						name="<?php echo esc_attr( "plsr_speculation_rules[{$args['field']}]" ); ?>"
						type="radio"
						value="<?php echo esc_attr( $slug ); ?>"
						<?php checked( $value, $slug ); ?>
					>
					<?php echo esc_html( $label ); ?>
				</label>
			</p>
		<?php endforeach; ?>

		<p class="description" style="max-width: 800px;">
			<?php
			echo wp_kses(
				$args['description'],
				array(
					'a' => array(
						'href'   => array(),
						'target' => array(),
					),
				)
			);
			?>
		</p>

		<?php if ( $show_notice ) : ?>
			<div id="plsr-auth-notice" class="notice <?php echo $show_warning ? 'notice-warning' : 'notice-info'; ?> inline">
				<p>
					<strong id="plsr-notice-label" <?php echo $show_warning ? '' : 'hidden'; ?>><?php esc_html_e( 'Warning: ', 'speculation-rules' ); ?></strong>
					<?php
					echo wp_kses(
						sprintf(
							/* translators: %s: URL to persistent object cache documentation */
							__( 'Enabling speculative loading for authenticated users without a persistent object cache may significantly increase server load. Consider setting up a <a href="%s" target="_blank">persistent object cache</a> before enabling this feature for logged-in users.', 'speculation-rules' ),
							'https://developer.wordpress.org/advanced-administration/performance/optimization/#object-caching'
						),
						array(
							'a' => array(
								'href'   => array(),
								'target' => array(),
							),
						)
					);
					?>
				</p>
			</div>
		<?php endif; ?>
	</fieldset>

	<?php
	if ( $show_notice ) {
		wp_print_inline_script_tag(
			sprintf(
				'const authRadios = document.querySelectorAll( ".plsr-auth-radio" );
				const noticeDiv = document.getElementById( "plsr-auth-notice" );
				const noticeLabel = document.getElementById( "plsr-notice-label" );
				const authenticatedOptions = %s;
				
				if ( authRadios.length && noticeDiv && noticeLabel ) {
					authRadios.forEach( function ( radio ) {
						radio.addEventListener( "change", function ( e ) {
							const isAuthOption = authenticatedOptions.includes( e.target.value );

							if ( isAuthOption ) {
								noticeDiv.classList.remove( "notice-info" );
								noticeDiv.classList.add( "notice-warning" );
								noticeLabel.hidden = false;
							} else {
								noticeDiv.classList.remove( "notice-warning" );
								noticeDiv.classList.add( "notice-info" );
								noticeLabel.hidden = true;
							}
						} );
					} );
				}',
				wp_json_encode( $authenticated_options, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES )
			),
			array( 'type' => 'module' )
		);
	}
}

/**
 * Adds a settings link to the plugin's action links.
 *
 * @since 1.2.1
 *
 * @param string[]|mixed $links An array of plugin action links.
 * @return string[]|mixed The modified list of actions.
 */
function plsr_add_settings_action_link( $links ) {
	if ( ! is_array( $links ) ) {
		return $links;
	}

	return array_merge(
		array(
			'settings' => sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( admin_url( 'options-reading.php#speculative-loading' ) ),
				esc_html__( 'Settings', 'speculation-rules' )
			),
		),
		$links
	);
}
add_filter( 'plugin_action_links_' . SPECULATION_RULES_MAIN_FILE, 'plsr_add_settings_action_link' );
