[Optimization Detective Documentation](./README.md):

# Optimization Detective Hooks

## Actions

### Action: `od_init` (argument: plugin version)

Fires when the Optimization Detective is initializing. This action is useful for loading extension code that depends on Optimization Detective to be running. The version of the plugin is passed as the sole argument so that if the required version is not present, the callback can short circuit.

### Action: `od_register_tag_visitors` (argument: `OD_Tag_Visitor_Registry`)

Fires to register tag visitors before walking over the document to perform optimizations.

For example, to register a new tag visitor that targets `H1` elements:

```php
add_action(
	'od_register_tag_visitors',
	static function ( OD_Tag_Visitor_Registry $registry ) {
		$registry->register(
			'my-plugin/h1',
			static function ( OD_Tag_Visitor_Context $context ): bool {
				if ( $context->processor->get_tag() !== 'H1' ) {
					return false;
				}
				// Now optimize based on stored URL Metrics in $context->url_metric_group_collection.
				// ...

				// Returning true causes the tag to be tracked in URL Metrics. If there is no need
				// for this, as in there is no reference to $context->url_metric_group_collection
				// in a tag visitor, then this can instead return false.
				return true;
			}
		);
	}
);
```

Refer to [Image Prioritizer](https://github.com/WordPress/performance/tree/trunk/plugins/image-prioritizer) and [Embed Optimizer](https://github.com/WordPress/performance/tree/trunk/plugins/embed-optimizer) for real world examples of how tag visitors are used. Registered tag visitors need only be callables, so in addition to providing a closure you may provide a `callable-string` or even a class which has an `__invoke()` method.

### Action: `od_url_metric_stored` (argument: `OD_URL_Metric_Store_Request_Context`)

Fires whenever a URL Metric was successfully stored.

The supplied context object includes these properties:

* `$request`: The `WP_REST_Request` for storing the URL Metric.
* `$post_id`: The post ID for the `od_url_metric` post.
* `$url_metric`: The newly-stored URL Metric.
* `$url_metric_group`: The viewport group that the URL Metric was added to.
* `$url_metric_group_collection`: The `OD_URL_Metric_Group_Collection` instance to which the URL Metric was added.

## Filters

### Filter: `od_use_web_vitals_attribution_build` (default: `false`)

Filters whether to use the web-vitals.js build with attribution.

When using the attribution build of web-vitals, the metric object passed to report callbacks registered via
`onTTFB`, `onFCP`, `onLCP`, `onCLS`, and `onINP` will include an additional [attribution property](https://github.com/GoogleChrome/web-vitals#attribution).
For details, please refer to the [web-vitals documentation](https://github.com/GoogleChrome/web-vitals).

For example, to opt in to using the attribution build:

```php
add_filter( 'od_use_web_vitals_attribution_build', '__return_true' );
```

Note that the attribution build is slightly larger than the standard build, so this is why it is not used by default.
The additional attribution data is made available to client-side extension script modules registered via the `od_extension_module_urls` filter.

### Filter: `od_breakpoint_max_widths` (default: `array(480, 600, 782)`)

Filters the breakpoint max widths to group URL Metrics for various viewports. Each number represents the maximum width (inclusive) for a given breakpoint. So if there is one number, 480, then this means there will be two viewport groupings, one for 0\<=480, and another \>480. If instead there are the two breakpoints defined, 480 and 782, then this means there will be three viewport groups of URL Metrics, one for 0\<=480 (i.e. mobile), another 481\<=782 (i.e. phablet/tablet), and another \>782 (i.e. desktop).

These default breakpoints are reused from Gutenberg which appear to be used the most in media queries that affect frontend styles.

### Filter: `od_can_optimize_response` (default: boolean condition, see below)

Filters whether the current response can be optimized. By default, detection and optimization are only performed when:

1. It’s not a search template (`is_search()`).
2. It’s not a post embed template (`is_embed()`).
3. It’s not the Customizer preview (`is_customize_preview()`)
4. It’s not the response to a `POST` request.
5. There is at least one queried post on the page. This is used to facilitate the purging of page caches after a new URL Metric is stored.

To force every response to be optimized regardless of the conditions above, you can do:

```php
add_filter( 'od_can_optimize_response', '__return_true' );
```

### Filter: `od_url_metrics_breakpoint_sample_size` (default: 3)

Filters the sample size for a breakpoint's URL Metrics on a given URL. The sample size must be greater than zero. You can increase the sample size if you want better guarantees that the applied optimizations will be accurate. During development, it may be helpful to reduce the sample size to 1 (along with setting the `od_url_metric_storage_lock_ttl` and `od_url_metric_freshness_ttl` filters below) so that you don't have to keep reloading the page to collect new URL Metrics to flush out stale ones during active development:

```php
add_filter( 'od_url_metrics_breakpoint_sample_size', function (): int {
	return 1;
} );
```

### Filter: `od_url_metric_storage_lock_ttl` (default: 1 minute in seconds)

Filters how long a given IP is locked from submitting another metric-storage REST API request. Filtering the TTL to zero will disable any metric storage locking. This is useful, for example, to disable locking when a user is logged-in with code like the following:

```php
add_filter( 'od_metrics_storage_lock_ttl', function ( int $ttl ): int {
	return is_user_logged_in() ? 0 : $ttl;
} );
```

During development this is useful to set to zero so you can quickly collect new URL Metrics by reloading the page without having to wait for the storage lock to release:

```php
add_filter( 'od_metrics_storage_lock_ttl', function ( int $ttl ): int {
	return 0;
} );
```

### Filter: `od_url_metric_freshness_ttl` (default: 1 day in seconds)

Filters the freshness age (TTL) for a given URL Metric. The freshness TTL must be at least zero, in which it considers URL Metrics to always be stale. In practice, the value should be at least an hour. If your site content does not change frequently, you may want to increase the TTL to a week:

```php
add_filter( 'od_url_metric_freshness_ttl', static function (): int {
	return WEEK_IN_SECONDS;
} );
```

During development, this can be useful to set to zero so that you don't have to wait for new URL Metrics to be requested when engineering a new optimization:

```php
add_filter( 'od_url_metric_freshness_ttl', static function (): int {
	return 0;
} );
```

### Filter: `od_minimum_viewport_aspect_ratio` (default: 0.4)

Filters the minimum allowed viewport aspect ratio for URL Metrics.

The 0.4 value is intended to accommodate the phone with the greatest known aspect ratio at 21:9 when rotated 90 degrees to 9:21 (0.429). During development when you have the DevTools console open on the right, the viewport aspect ratio will be smaller than normal. In this case, you may want to set this to 0:

```php
add_filter( 'od_minimum_viewport_aspect_ratio', static function (): int {
	return 0;
} );
```

### Filter: `od_maximum_viewport_aspect_ratio` (default: 2.5)

Filters the maximum allowed viewport aspect ratio for URL Metrics.

The 2.5 value is intended to accommodate the phone with the greatest known aspect ratio at 21:9 (2.333).

During development when you have the DevTools console open on the bottom, for example, the viewport aspect ratio will be larger than normal. In this case, you may want to increase the maximum aspect ratio:

```php
add_filter( 'od_maximum_viewport_aspect_ratio', static function (): int {
	return 5;
} );
```

### Filter: `od_template_output_buffer` (default: the HTML response)

Filters the template output buffer prior to sending to the client. This filter is added to implement [\#43258](https://core.trac.wordpress.org/ticket/43258) in WordPress core.

### Filter: `od_url_metric_schema_element_item_additional_properties` (default: empty array)

Filters additional schema properties which should be allowed for an element's item in a URL Metric.

For example to add a `resizedBoundingClientRect` property:

```php
<?php
add_filter(
	'od_url_metric_schema_element_item_additional_properties',
	static function ( array $additional_properties ): array {
		$additional_properties['resizedBoundingClientRect'] = array(
			'type'       => 'object',
			'properties' => array_fill_keys(
				array(
					'width',
					'height',
					'x',
					'y',
					'top',
					'right',
					'bottom',
					'left',
				),
				array(
					'type'     => 'number',
					'required' => true,
				)
			),
		);
		return $additional_properties;
	}
);
```

See also [example usage](https://github.com/WordPress/performance/blob/6bb8405c5c446e3b66c2bfa3ae03ba61b188bca2/plugins/embed-optimizer/hooks.php#L81-L110) in Embed Optimizer.

### Filter: `od_url_metric_schema_root_additional_properties` (default: empty array)

Filters additional schema properties which should be allowed at the root of a URL Metric.

The usage here is the same as the previous filter, except it allows new properties to be added to the root of the URL Metric and not just to one of the object items in the `elements` property.

### Filter: `od_extension_module_urls` (default: empty array of strings)

Filters the list of extension script module URLs to import when performing detection.

For example:

```php
add_filter(
	'od_extension_module_urls',
	static function ( array $extension_module_urls ): array {
		$extension_module_urls[] = add_query_arg( 'ver', '1.0', plugin_dir_url( __FILE__ ) . 'detect.js' );
		return $extension_module_urls;
	}
);
```

See also [example usage](https://github.com/WordPress/performance/blob/6bb8405c5c446e3b66c2bfa3ae03ba61b188bca2/plugins/embed-optimizer/hooks.php#L128-L144) in Embed Optimizer. Note in particular the structure of the plugin’s [detect.js](https://github.com/WordPress/performance/blob/trunk/plugins/embed-optimizer/detect.js) script module, how it exports `initialize` and `finalize` functions which Optimization Detective then calls when the page loads and when the page unloads, at which time the URL Metric is constructed and sent to the server for storage. Refer also to the [TypeScript type definitions](https://github.com/WordPress/performance/blob/trunk/plugins/optimization-detective/types.ts).

### Filter: `od_current_url_metrics_etag_data` (default: array with `tag_visitors` key)

Filters the data that goes into computing the current ETag for URL Metrics.

The ETag is a unique identifier that changes whenever the underlying data used to generate it changes. By default, the ETag calculation includes the names of registered tag visitors. This ensures that when a new Optimization Detective-dependent plugin is activated (like [Image Prioritizer](https://wordpress.org/plugins/image-prioritizer/) or [Embed Optimizer](https://wordpress.org/plugins/embed-optimizer/)), any existing URL Metrics are immediately considered stale. This happens because the newly registered tag visitors alter the ETag calculation, making it different from the stored ones.

When the ETag for URL Metrics in a complete viewport group no longer matches the current environment's ETag, new URL Metrics will then begin to be collected until there are no more stored URL Metrics with the old ETag. These new URL Metrics will include data relevant to the newly activated plugins and their tag visitors.
