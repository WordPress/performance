[Optimization Detective Documentation](./README.md):

# Optimization Detective Introduction

## Background

WordPress uses [server-side heuristics](https://make.wordpress.org/core/2023/07/13/image-performance-enhancements-in-wordpress-6-3/) to make educated guesses about which images are likely to be in the initial viewport. Likewise, it uses server-side heuristics to identify a hero image which is likely to be the Largest Contentful Paint (LCP) element. To optimize page loading, it avoids lazy loading any of these images while also adding `fetchpriority=high` to the hero image. When these heuristics are applied successfully, the LCP metric for page loading can be improved 5-10%. Unfortunately, however, there are limitations to the heuristics that make the correct identification of which image is the LCP element only about 50% effective. See [Analyzing the Core Web Vitals performance impact of WordPress 6.3 in the field](https://make.wordpress.org/core/2023/09/19/analyzing-the-core-web-vitals-performance-impact-of-wordpress-6-3-in-the-field/). For example, it is [common](https://github.com/GoogleChromeLabs/wpp-research/pull/73) for the LCP element to vary between different viewport widths, such as desktop versus mobile. Since WordPress's heuristics are completely server-side it has no knowledge of how the page is actually laid out, and it cannot prioritize loading of images according to the client's viewport width.

In order to increase the accuracy of identifying the LCP element, including across various client viewport widths, this plugin gathers metrics from real users (RUM) to detect the actual LCP element and then use this information to optimize the page for future visitors so that the loading of the LCP element is properly prioritized. This is the purpose of Optimization Detective. The approach is heavily inspired by Philip Walton’s [Dynamic LCP Priority: Learning from Past Visits](https://philipwalton.com/articles/dynamic-lcp-priority/). See also the initial exploration document that laid out this project: [Image Loading Optimization via Client-side Detection](https://docs.google.com/document/u/1/d/16qAJ7I_ljhEdx2Cn2VlK7IkiixobY9zNn8FXxN9T9Ls/view).

## Technical Foundation

At the core of Optimization Detective is the “URL Metric”, information about a page according to how it was loaded by a client with a specific viewport width. This includes which elements were visible in the initial viewport and which one was the LCP element. The URL Metric data is also extensible. Each URL on a site can have an associated set of these URL Metrics (stored in a custom post type) which are gathered from the visits of real users. It gathers samples of URL Metrics which are grouped according to WordPress's default responsive breakpoints:

1. Mobile: 0-480px
2. Phablet: 481-600px
3. Tablet: 601-782px
4. Desktop: \>782px

When no more URL Metrics are needed for a URL due to the sample size being obtained for the viewport group, it discontinues serving the JavaScript to gather the metrics (leveraging the [web-vitals.js](https://github.com/GoogleChrome/web-vitals) library). With the URL Metrics in hand, the output-buffered page is sent through the HTML Tag Processor and—when the [Image Prioritizer](https://wordpress.org/plugins/image-prioritizer/) dependent plugin is installed—the images which were the LCP element for various breakpoints will get prioritized with high-priority preload links (along with `fetchpriority=high` on the actual `img` tag when it is the common LCP element across all breakpoints). LCP elements with background images added via inline `background-image` styles are also prioritized with preload links.

URL Metrics have a “freshness TTL” after which they will be stale and the JavaScript will be served again to start gathering metrics again to ensure that the right elements continue to get their loading prioritized. When a URL Metrics custom post type hasn't been touched in a while, it is automatically garbage-collected.

👉 **Note:** This plugin optimizes pages for actual visitors, and it depends on visitors to optimize pages (since URL Metrics need to be collected). As such, you won't see optimizations applied immediately after activating the plugin (and dependent plugin(s)). And since administrator users are not normal visitors typically, optimizations are not applied for admins by default (but this can be overridden with the `od_can_optimize_response` filter below). URL Metrics are not collected for administrators because it is likely that additional elements will be present on the page which are not also shown to non-administrators, meaning the URL Metrics could not reliably be reused between them.

When the `WP_DEBUG` constant is enabled, additional logging for Optimization Detective is added to the browser console.
