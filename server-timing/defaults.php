<?php
/**
 * Server-Timing API default metrics
 *
 * @package performance-lab
 * @since 1.8.0
 */

/**
 * Registers the default Server-Timing metrics for before rendering the template.
 *
 * These metrics should be registered as soon as possible.
 *
 * @since 1.8.0
 */
function perflab_register_default_server_timing_before_template_metrics() {
	$calculate_before_template_metrics = static function() {
		// WordPress execution prior to serving the template.
		perflab_server_timing_register_metric(
			'before-template',
			array(
				'measure_callback' => static function( $metric ) {
					// The 'timestart' global is set right at the beginning of WordPress execution.
					$metric->set_value( ( microtime( true ) - $GLOBALS['timestart'] ) * 1000.0 );
				},
				'access_cap'       => 'exist',
			)
		);

		// SQL query time is only measured if the SAVEQUERIES constant is set to true.
		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
			// WordPress database query time before template.
			perflab_server_timing_register_metric(
				'before-template-db-queries',
				array(
					'measure_callback' => static function( $metric ) {
						// This should never happen, but some odd database implementations may be doing it wrong.
						if ( ! isset( $GLOBALS['wpdb']->queries ) || ! is_array( $GLOBALS['wpdb']->queries ) ) {
							return;
						}

						// Store this value in a global to later subtract it from total query time after template.
						$GLOBALS['perflab_query_time_before_template'] = array_reduce(
							$GLOBALS['wpdb']->queries,
							static function( $acc, $query ) {
								return $acc + $query[1];
							},
							0.0
						);
						$metric->set_value( $GLOBALS['perflab_query_time_before_template'] * 1000.0 );
					},
					'access_cap'       => 'exist',
				)
			);
		}
	};

	// If output buffering is used, explicitly measure only the time before serving the template.
	// Otherwise, the Server-Timing header will be sent before serving the template anyway.
	// We need to check for output buffer usage in the callback so that e.g. plugins and theme can
	// modify the value prior to the check.
	add_filter(
		'template_include',
		static function( $passthrough ) use ( $calculate_before_template_metrics ) {
			if ( perflab_server_timing_use_output_buffer() ) {
				$calculate_before_template_metrics();
			}
			return $passthrough;
		},
		PHP_INT_MAX
	);
	add_action(
		'perflab_server_timing_send_header',
		static function() use ( $calculate_before_template_metrics ) {
			if ( ! perflab_server_timing_use_output_buffer() ) {
				$calculate_before_template_metrics();
			}
		},
		PHP_INT_MAX
	);

	// Measure duration of autoloaded options query.
	// Requires the Performance Lab object-cache.php drop-in to be present in order to work,
	// which is why the constant is checked below.
	if ( PERFLAB_OBJECT_CACHE_DROPIN_VERSION ) {
		add_filter(
			'query',
			static function( $query ) {
				global $wpdb;
				if ( "SELECT option_name, option_value FROM $wpdb->options WHERE autoload = 'yes'" !== $query ) {
					return $query;
				}
				// In case the autoloaded options query is run again, prevent re-registering it and do not measure again.
				if ( perflab_server_timing()->has_registered_metric( 'load-alloptions-query' ) ) {
					return $query;
				}
				perflab_server_timing_register_metric(
					'load-alloptions-query',
					array(
						'measure_callback' => static function( $metric ) {
							$metric->measure_before();
							add_filter(
								'pre_cache_alloptions',
								static function( $passthrough ) use ( $metric ) {
									$metric->measure_after();
									return $passthrough;
								}
							);
						},
						'access_cap'       => 'exist',
					)
				);
				return $query;
			}
		);
	}
}
perflab_register_default_server_timing_before_template_metrics();

/**
 * Registers the default Server-Timing metrics while rendering the template.
 *
 * These metrics should be registered at a later point, e.g. the 'wp_loaded' action.
 * They will only be registered if the Server-Timing API is configured to use an
 * output buffer for the site's template.
 *
 * @since 1.8.0
 */
function perflab_register_default_server_timing_template_metrics() {
	// Template-related metrics can only be recorded if output buffering is used.
	if ( ! perflab_server_timing_use_output_buffer() ) {
		return;
	}

	add_filter(
		'template_include',
		static function( $passthrough = null ) {
			// WordPress execution while serving the template.
			perflab_server_timing_register_metric(
				'template',
				array(
					'measure_callback' => static function( $metric ) {
						$metric->measure_before();
						add_action( 'perflab_server_timing_send_header', array( $metric, 'measure_after' ), PHP_INT_MAX );
					},
					'access_cap'       => 'exist',
				)
			);

			return $passthrough;
		},
		PHP_INT_MAX
	);

	add_action(
		'perflab_server_timing_send_header',
		static function() {
			// WordPress total load time.
			perflab_server_timing_register_metric(
				'total',
				array(
					'measure_callback' => static function( $metric ) {
						// The 'timestart' global is set right at the beginning of WordPress execution.
						$metric->set_value( ( microtime( true ) - $GLOBALS['timestart'] ) * 1000.0 );
					},
					'access_cap'       => 'exist',
				)
			);
		}
	);

	// SQL query time is only measured if the SAVEQUERIES constant is set to true.
	if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
		add_action(
			'perflab_server_timing_send_header',
			static function() {
				// WordPress database query time within template.
				perflab_server_timing_register_metric(
					'template-db-queries',
					array(
						'measure_callback' => static function( $metric ) {
							// This global should typically be set when this is called, but check just in case.
							if ( ! isset( $GLOBALS['perflab_query_time_before_template'] ) ) {
								return;
							}

							// This should never happen, but some odd database implementations may be doing it wrong.
							if ( ! isset( $GLOBALS['wpdb']->queries ) || ! is_array( $GLOBALS['wpdb']->queries ) ) {
								return;
							}

							$total_query_time = array_reduce(
								$GLOBALS['wpdb']->queries,
								static function( $acc, $query ) {
									return $acc + $query[1];
								},
								0.0
							);
							$metric->set_value( ( $total_query_time - $GLOBALS['perflab_query_time_before_template'] ) * 1000.0 );
						},
						'access_cap'       => 'exist',
					)
				);
			},
			PHP_INT_MAX
		);
	}
}
add_action( 'wp_loaded', 'perflab_register_default_server_timing_template_metrics' );

/**
 * Registers additional Server-Timing metrics as configured in the setting.
 *
 * These metrics should be registered as soon as possible. They can be added
 * and modified in the "Tools > Server-Timing" screen.
 *
 * @since n.e.x.t
 */
function perflab_register_additional_server_timing_metrics_from_setting() {
	$options = (array) get_option( PERFLAB_SERVER_TIMING_SETTING, array() );

	if ( isset( $options['benchmarking_actions'] ) ) {
		foreach ( $options['benchmarking_actions'] as $action ) {
			$metric_slug = 'action-' . $action;

			$measure_callback = static function( $metric ) use ( $action ) {
				$metric->measure_before();
				add_action( $action, array( $metric, 'measure_after' ), PHP_INT_MAX, 0 );
			};

			add_action(
				$action,
				static function() use ( $metric_slug, $measure_callback ) {
					perflab_server_timing_register_metric(
						$metric_slug,
						array(
							'measure_callback' => $measure_callback,
							'access_cap'       => 'exist',
						)
					);
				},
				// phpcs:ignore PHPCompatibility.Constants.NewConstants.php_int_minFound
				defined( 'PHP_INT_MIN' ) ? PHP_INT_MIN : ~PHP_INT_MAX
			);
		}
	}

	if ( isset( $options['benchmarking_filters'] ) ) {
		foreach ( $options['benchmarking_filters'] as $filter ) {
			$metric_slug = 'filter-' . $filter;

			$measure_callback = static function( $metric ) use ( $filter ) {
				$metric->measure_before();
				add_filter(
					$filter,
					static function( $passthrough ) use ( $metric ) {
						$metric->measure_after();
						return $passthrough;
					},
					PHP_INT_MAX
				);
			};

			add_filter(
				$filter,
				static function( $passthrough ) use ( $metric_slug, $measure_callback ) {
					perflab_server_timing_register_metric(
						$metric_slug,
						array(
							'measure_callback' => $measure_callback,
							'access_cap'       => 'exist',
						)
					);
					return $passthrough;
				},
				// phpcs:ignore PHPCompatibility.Constants.NewConstants.php_int_minFound
				defined( 'PHP_INT_MIN' ) ? PHP_INT_MIN : ~PHP_INT_MAX
			);
		}
	}
}

/*
 * If this file is loaded from the Server-Timing logic in the object-cache.php
 * drop-in, it must not call this function right away since otherwise the cache
 * will not be loaded yet.
 */
if ( ! did_action( 'muplugins_loaded' ) ) {
	add_action( 'muplugins_loaded', 'perflab_register_additional_server_timing_metrics_from_setting' );
} else {
	perflab_register_additional_server_timing_metrics_from_setting();
}
