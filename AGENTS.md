# Performance Lab

This is a monorepo for the WordPress Performance Team, containing a collection of standalone performance feature plugins. Refer to the [Performance Lab handbook](https://make.wordpress.org/performance/handbook/performance-lab/) for more details. 

## Project Overview

* **Purpose:** To develop and maintain a suite of plugins that improve the performance of WordPress sites. All should be considered potential for future candidates for merging into WordPress core.
* **Technologies:** PHP, JavaScript, CSS, a variety of testing and linting tools.

### Project Structure

* `/bin`: Custom CLI commands and scripts for certain development workflows.
* `/plugins`: The actual WordPress plugins that are developed in this monorepo.
* `/plugins/*`: An individual WordPress plugin folder.
* `/plugins/*/tests`: PHPUnit tests for the specific WordPress plugin.
* `/tools`: Setup and configuration files for various tools, such as linting and testing.

## Building and Running

### Prerequisites

* [Node.js and npm](https://nodejs.org/en/)
* [Docker](https://www.docker.com/)
* [Composer](https://getcomposer.org/)

### Installation

1. Run `npm install` to install the Node.js dependencies.
2. Run `composer install` to install the PHP dependencies.
3. Run `npm run build` to do an initial build of the assets.

### Building

* To build the JavaScript and CSS assets: `npm run build`
* To build all plugins and place into the `build` directory: `npm run build-plugins`
* To build a specific plugin: `npm run build:plugin:<plugin-slug>` (e.g., `npm run build:plugin:performance-lab`)
* To build ZIP files for distribution: `npm run build-plugins:zip`

### Running a Local Environment

This project uses `@wordpress/env` to create a local development environment.

* Check if the environment is already running: `npm run wp-env status`
* Start the environment: `npm run wp-env start`
* Stop the environment: `npm run wp-env stop`

The environment will by default be located at `http://localhost:8888` but this can be overridden by `.wp-env.override.json`.

## Code Style

In general, the [coding standards for WordPress](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/) should be followed:

* [CSS Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/css/)
* [HTML Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/html/)
* [JavaScript Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/)
* [PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)

Note that for the JavaScript Coding Standards, the code should also be formatted using Prettier, specifically the [wp-prettier](https://www.npmjs.com/package/wp-prettier) fork with the `--paren-spacing` option which inserts extra spaces inside parentheses.

For the HTML Coding Standards, disregard the guidance that void/empty tags should be self-closing, such as `IMG`, `BR`, `LINK`, or `META`. This is only relevant for XML (XHTML), not HTML. So instead of `<br />` this should only use `<br>`, for example.

Additionally, the [inline documentation standards for WordPress](https://developer.wordpress.org/coding-standards/inline-documentation-standards/) should be followed:

* [PHP Documentation Standards](https://developer.wordpress.org/coding-standards/inline-documentation-standards/php/)
* [JavaScript Documentation Standards](https://developer.wordpress.org/coding-standards/inline-documentation-standards/javascript/)

Note that `lint-staged` will be used to automatically run code quality checks with the tooling based on the staged files.

### Indentation

In general, indentation should use tabs. Refer to `.editorconfig` in the project root for specifics.

### Inline Documentation

It is expected for new code introduced to have `@since` tags with the `n.e.x.t` placeholder version. It will get replaced with the actual version at the time of release. Do not add any code review comments to such code.

Every file, function, class, method constant, and global variable must have an associated docblock with a `@since` tag.

### PHP

Follow coding conventions in WordPress core. Namespaces are generally not used, as they are not normally used in WordPress core code. Procedural programming patterns are favored where classes play a supporting role, rather than everything being written in OOP.

Whenever possible, the most specific PHP type hints should be used, when backward compatible with PHP 7.2, the minimum version of PHP supported by WordPress and this repository. When native PHP type cannot be used, PHPStan's [PHPDoc Types](https://phpstan.org/writing-php-code/phpdoc-types) should be used, including not only the basic types but also subtypes like `non-empty-string`, [integer ranges](https://phpstan.org/writing-php-code/phpdoc-types#integer-ranges), [general arrays](https://phpstan.org/writing-php-code/phpdoc-types#general-arrays), and especially [array shapes](https://phpstan.org/writing-php-code/phpdoc-types#array-shapes). The types should comply with PHPStan's level 10. The one exception for using PHP types is whenever a function is used as a filter. Since plugins can supply any value at all when filtering, use the expected type with a union to `mixed`. The first statement in the function in this case must always check the type, and if it is not the expected type, override it to be so.

Never render HTML `SCRIPT` tags directly in HTML. Always use the relevant APIs in WordPress for adding scripts, including `wp_enqueue_script()`, `wp_add_inline_script()`, `wp_localize_script()`, `wp_print_script_tag()`, `wp_print_inline_script_tag()`, `wp_enqueue_script_module()` among others. Favor modules over classic scripts.

Here is an example PHP file with various conventions demonstrated.

```php
/**
 * Filtering functions for the Bar plugin.
 *
 * @since n.e.x.t
 * @package Bar
 */

/**
 * Filters post title to be upper case.
 *
 * @since n.e.x.t
 *
 * @param string|mixed $title   Title.
 * @param positive-int $post_id Post ID.
 * @return string Upper-cased title.
 */
function bar_filter_title_uppercase( $title, int $post_id ): string {
	if ( ! is_string( $title ) ) {
		$title = '';
	}
	/**
	 * Because plugins do bad things.
	 *
	 * @var string $title
	 */

	return strtoupper( $title );
}
add_filter( 'the_title', 'bar_filter_title_uppercase', 10, 2 );
```

### JavaScript

All JavaScript code should be written with JSDoc comments. All function parameters, return values, and other types should use [TypeScript in JSDoc](https://www.typescriptlang.org/docs/handbook/jsdoc-supported-types.html).

JavaScript code should be written using ES modules. This JS code must be runnable as-is without having to go through a build step, so it must be plain JavaScript and not TypeScript. The project _may_ also distribute minified versions of these JS files.

Here's an example JS file:

```js
/**
 * Foo module for Optimization Detective
 *
 * This extension optimizes the foo performance feature.
 *
 * @since n.e.x.t
 */

export const name = 'Foo';

/**
 * @typedef {import("web-vitals").LCPMetric} LCPMetric
 * @typedef {import("../optimization-detective/types.ts").InitializeCallback} InitializeCallback
 * @typedef {import("../optimization-detective/types.ts").InitializeArgs} InitializeArgs
 */

/**
 * Initializes extension.
 *
 * @since n.e.x.t
 *
 * @type {InitializeCallback}
 * @param {InitializeArgs} args Args.
 */
export async function initialize( { log, onLCP, extendRootData } ) {
  onLCP(
    ( metric ) => {
      handleLCPMetric( metric, extendRootData, log );
    }
  );
}

// ... function definition for handleLCPMetric omitted ...
```

### Static Analysis Commands

* **PHPStan**: `npm run phpstan`
* **TypeScript**: `npm run tsc`

### Linting Commands

* **JavaScript:** `npm run lint-js`
* **PHP:** `npm run lint-php`

### Formatting Commands

* **JavaScript:** `npm run format-js`
* **PHP:** `npm run format-php`

### Testing Commands

* **End-to-end (E2E) tests:** `npm run test-e2e`
* **PHP tests:** `npm run test-php`
* **PHP tests (multisite):** `npm run test-php-multisite`
