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
	 * [--provider-index=<int>]
	 * : Index of the provider.
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--subtype-index=<int>]
	 * : Index of the subtype.
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--page-number=<int>]
	 * : Page number for pagination.
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--offset-within-page=<int>]
	 * : Offset within the current page.
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--batch-size=<int>]
	 * : Number of items to return.
	 * ---
	 * default: 10
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
	 *     # List 20 URL metrics with specific pagination parameters
	 *     $ wp od get_url_batch --provider-index=0 --subtype-index=0 --page-number=1 --offset-within-page=0 --batch-size=20 --format=json
	 *
	 * @param array<string>         $args       Command arguments.
	 * @param array<string, string> $assoc_args Command associated arguments.
	 */
	public function get_url_batch( array $args, array $assoc_args ): void {
		$cursor = array(
			'provider_index'     => isset( $assoc_args['provider-index'] ) ? (int) $assoc_args['provider-index'] : 0,
			'subtype_index'      => isset( $assoc_args['subtype-index'] ) ? (int) $assoc_args['subtype-index'] : 0,
			'page_number'        => isset( $assoc_args['page-number'] ) ? (int) $assoc_args['page-number'] : 0,
			'offset_within_page' => isset( $assoc_args['offset-within-page'] ) ? (int) $assoc_args['offset-within-page'] : 0,
			'batch_size'         => isset( $assoc_args['batch-size'] ) ? (int) $assoc_args['batch-size'] : 10,
		);

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		if ( function_exists( '\\WP_CLI\\Utils\\format_items' ) ) {
			WP_CLI\Utils\format_items( $format, array( od_generate_batch_for_url_metrics_priming_mode( $cursor ) ), array( 'urlGroups', 'cursor', 'verificationToken', 'isDebug' ) );
		}
	}
}

// Register the WP-CLI command.
WP_CLI::add_command( 'od', new OD_WP_CLI() );
