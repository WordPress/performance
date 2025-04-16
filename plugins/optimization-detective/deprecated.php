<?php
/**
 * Deprecated functions and constants.
 *
 * @package optimization-detective
 *
 * @since 1.0.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Namespace for optimization-detective.
 *
 * @since 0.1.0
 * @access private
 * @deprecated n.e.x.t This constant should not be used, instead use OD_REST_URL_Metrics_Store_Endpoint::ROUTE_NAMESPACE method.
 * @var string
 */
const OD_REST_API_NAMESPACE = 'optimization-detective/v1';

/**
 * Route for storing a URL Metric.
 *
 * Note the `:store` art of the endpoint follows Google's guidance in AIP-136 for the use of the POST method in a way
 * that does not strictly follow the standard usage. Namely, submitting a POST request to this endpoint will either
 * create a new `od_url_metrics` post, or it will update an existing post if one already exists for the provided slug.
 *
 * @since 0.1.0
 * @access private
 * @link https://google.aip.dev/136
 * @deprecated n.e.x.t This constant should not be used, instead use OD_REST_URL_Metrics_Store_Endpoint::ROUTE_BASE method.
 * @var string
 */
const OD_URL_METRICS_ROUTE = '/url-metrics:store';
// @codeCoverageIgnoreEnd
