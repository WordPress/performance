<?php
/**
 * Settings for the AI Performance Advisor plugin.
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
 * Sanitizes the plugin settings.
 *
 * @since 1.0.0
 *
 * @param mixed $input Raw settings input.
 * @return array{ include_pagespeed: bool, pagespeed_api_key: string } Sanitized settings.
 */
function aipa_sanitize_settings( $input ): array {
	$defaults = aipa_get_default_settings();

	if ( ! is_array( $input ) ) {
		return $defaults;
	}

	return array(
		'include_pagespeed' => isset( $input['include_pagespeed'] ) && (bool) $input['include_pagespeed'],
		'pagespeed_api_key' => isset( $input['pagespeed_api_key'] ) ? sanitize_text_field( (string) $input['pagespeed_api_key'] ) : '',
	);
}

/**
 * Registers the plugin setting.
 *
 * @since 1.0.0
 */
function aipa_register_setting(): void {
	register_setting(
		'general',
		'aipa_settings',
		array(
			'type'              => 'object',
			'description'       => __( 'AI Performance Advisor configuration.', 'ai-performance-advisor' ),
			'sanitize_callback' => 'aipa_sanitize_settings',
			'default'           => aipa_get_default_settings(),
			'show_in_rest'      => array(
				'schema' => array(
					'type'                 => 'object',
					'properties'           => array(
						'include_pagespeed' => array(
							'type' => 'boolean',
						),
						'pagespeed_api_key' => array(
							'type' => 'string',
						),
					),
					'additionalProperties' => false,
				),
			),
		)
	);
}
add_action( 'admin_init', 'aipa_register_setting' );

/**
 * Adds the settings section and fields to the General settings screen.
 *
 * @since 1.0.0
 */
function aipa_add_settings_ui(): void {
	add_settings_section(
		'aipa_settings',
		__( 'AI Performance Advisor', 'ai-performance-advisor' ),
		static function (): void {
			?>
			<p class="description" id="ai-performance-advisor">
				<?php esc_html_e( 'Configure how the AI Performance Advisor gathers data for its analysis.', 'ai-performance-advisor' ); ?>
			</p>
			<?php
		},
		'general'
	);

	add_settings_field(
		'aipa_include_pagespeed',
		__( 'PageSpeed Insights', 'ai-performance-advisor' ),
		'aipa_render_include_pagespeed_field',
		'general',
		'aipa_settings'
	);

	add_settings_field(
		'aipa_pagespeed_api_key',
		__( 'PageSpeed Insights API key', 'ai-performance-advisor' ),
		'aipa_render_pagespeed_api_key_field',
		'general',
		'aipa_settings'
	);
}
add_action( 'admin_init', 'aipa_add_settings_ui' );

/**
 * Renders the "include PageSpeed Insights" checkbox field.
 *
 * @since 1.0.0
 */
function aipa_render_include_pagespeed_field(): void {
	$settings = aipa_get_settings();
	?>
	<label>
		<input type="checkbox" name="aipa_settings[include_pagespeed]" value="1" <?php checked( $settings['include_pagespeed'] ); ?> />
		<?php esc_html_e( 'Include a PageSpeed Insights (Lighthouse) snapshot of the home page in the analysis.', 'ai-performance-advisor' ); ?>
	</label>
	<?php
}

/**
 * Renders the PageSpeed Insights API key field.
 *
 * @since 1.0.0
 */
function aipa_render_pagespeed_api_key_field(): void {
	$settings = aipa_get_settings();
	?>
	<input
		type="text"
		class="regular-text"
		name="aipa_settings[pagespeed_api_key]"
		value="<?php echo esc_attr( $settings['pagespeed_api_key'] ); ?>"
		autocomplete="off"
	/>
	<p class="description">
		<?php esc_html_e( 'Optional. A PageSpeed Insights API key is not required at low volume, but providing one raises rate limits.', 'ai-performance-advisor' ); ?>
	</p>
	<?php
}

/**
 * Adds a settings link to the plugin's action links.
 *
 * @since 1.0.0
 *
 * @param string[]|mixed $links An array of plugin action links.
 * @return string[]|mixed The modified list of action links.
 */
function aipa_add_settings_action_link( $links ) {
	if ( ! is_array( $links ) ) {
		return $links;
	}

	return array_merge(
		array(
			'settings' => sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( admin_url( 'options-general.php#ai-performance-advisor' ) ),
				esc_html__( 'Settings', 'ai-performance-advisor' )
			),
		),
		$links
	);
}
add_filter( 'plugin_action_links_' . plugin_basename( AI_PERFORMANCE_ADVISOR_MAIN_FILE ), 'aipa_add_settings_action_link' );
