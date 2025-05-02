<?php
/**
 * WP-CLI commands for Optimization Detective.
 *
 * @package optimization-detective
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'WP_CLI' ) || ! class_exists( 'WP_CLI' ) ) {
	return;
}

/**
 * OD_WP_CLI class
 *
 * @since n.e.x.t
 */
class OD_WP_CLI {

	/**
	 * Gets batch of URLs that need to be primed.
	 *
	 * ## OPTIONS
	 *
	 * [--cursor=<string>]
	 * : JSON encoded cursor to paginate through the URLs.
	 * ---
	 * default: []
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format (table, json, csv, yaml)
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Get a batch of URLs that need to be primed
	 *     $ wp od get_url_batch --format=json
	 *
	 *     # List 20 URL metrics in JSON format
	 *     $ wp od get_url_batch --cursor='{"provider_index":0,"subtype_index":0,"page_number":1,"offset_within_page":0,"batch_size":10}' --format=json
	 *
	 * @param array<string>         $args       Command arguments.
	 * @param array<string, string> $assoc_args Command associated arguments.
	 */
	public function get_url_batch( array $args, array $assoc_args ): void {
		$cursor = array();
		if ( isset( $assoc_args['cursor'] ) ) {
			$cursor = json_decode( $assoc_args['cursor'], true );

			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $cursor ) ) {
				$cursor = array();
			}
		}
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		if ( function_exists( '\\WP_CLI\\Utils\\format_items' ) ) {
			WP_CLI\Utils\format_items( $format, array( od_generate_batch_for_url_metrics_priming_mode( $cursor ) ), array( 'batch', 'cursor', 'verificationToken', 'isDebug' ) );
		}
	}
}

// Register the WP-CLI command.
WP_CLI::add_command( 'od', new OD_WP_CLI() );
