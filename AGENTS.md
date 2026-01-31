# Performance Lab

This is a monorepo for the WordPress Performance Team, containing a collection of standalone performance feature plugins. Refer to the [Performance Lab handbook](https://make.wordpress.org/performance/handbook/performance-lab/) for more details. 

## Project Overview

* **Purpose:** To develop and maintain a suite of plugins that improve the performance of WordPress sites. All should be considered potential for future candidates for merging into WordPress core.
* **Technologies:** PHP, JavaScript, CSS, a variety of testing and linting tools.

### Project Structure

In this documentation, `<plugin-slug>` is a placeholder for one of the subdirectories under `/plugins`.

* `/bin`: Custom CLI commands and scripts for certain development workflows.
* `/plugins`: The actual WordPress plugins that are developed in this monorepo.
* `/plugins/<plugin-slug>`: An individual WordPress plugin folder.
* `/plugins/<plugin-slug>/tests`: PHPUnit tests for the specific WordPress plugin.
* `/tools`: Setup and configuration files for various tools, such as linting and testing.

## Building and Running

### Prerequisites

* [Node.js and npm](https://nodejs.org/en/)
* [Docker](https://www.docker.com/)
* [Composer](https://getcomposer.org/)

### Installation

1. Run `npm install` to install the Node.js dependencies.
2. Run `npx husky` to ensure pre-commit hook (using `lint-staged`) is installed.
3. Run `composer install` to install the PHP dependencies.
4. Run `npm run build` to do an initial build of the JS and CSS assets.

### Production Builds

* To build all plugins and place into the `build` directory: `npm run build-plugins`.
* To build a specific plugin: `npm run build:plugin:<plugin-slug>`.
* To build ZIP files for distribution: `npm run build-plugins:zip`

### Running Local Environment

This project uses `@wordpress/env` to create a local development environment.

* Start: `npx wp-env start` (with Xdebug: `npx wp-env start --xdebug`)
* Stop: `npx wp-env stop`
* Running a WP-CLI command: `npx wp-env run cli -- <command>` (e.g. `npx wp-env run cli -- wp post list`)

Web environment URLs:

* Development: `http://localhost:8888` (overridden by `port` in `.wp-env.override.json`)
* Test: `http://localhost:8889` (overridden by `testsPort` in `.wp-env.override.json`)

### Testing

* PHPUnit:
  * Run tests for all plugins: `npm run test-php` (and in multisite: `npm run test-php-multisite`)
  * Run tests for one plugin: `npm run test-php:<plugin-slug>` (and in multisite: `npm run test-php-multisite:<plugin-slug>`)
* End-to-end (E2E) tests:
  * Run tests for all plugins: `npm run test-e2e`
  * Run tests for one plugin: `npm run test-e2e:<plugin-slug>` (currently only `auto-sizes`)

### Formatting

* Format all files in the plugin: `npm run format`
* Format all PHP files: `composer format:all`
* Format all JavaScript, JSON, TypeScript, YAML files: `npm run format-js`

When possible, scope formatting to just the file/plugin being modified:

* Format JS files for a plugin: `npm run format-js plugins/<plugin-slug>` (or supply specific paths)
* Format all PHP files for a plugin: `composer format:<plugin-slug>`
* Format specific PHP files for a plugin: `composer format -- plugins/<plugin-slug>/phpcs.xml.dist plugins/<plugin-slug>/*.php`
* Format JavaScript, JSON, TypeScript, YAML files for a plugin: `npm run format-js plugins/<plugin-slug>` (or supply specific paths)

### Static Analysis

Static analysis involves linting (ESLint, PHPCS), PHPStan (`composer phpstan`), and TypeScript (`npx tsc`). These are run automatically via a pre-commit hook, but they can be run manually. See the [lint-staged configuration](./lint-staged.config.js) for how to invoke.

## Contributing a Change

Ensure all changed code passes static analysis checks and unit tests. When possible, include unit tests with each change. A bug fix should include a test that reproduces the original issue before following up with a commit to fix the issue.

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

In general, indentation should use tabs. Refer to [`.editorconfig`](./.editorconfig) in the project root for specifics.

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
