# Copilot Instructions for Performance Lab

This repository is a monorepo for the WordPress Performance Team, containing a collection of standalone performance feature plugins. When contributing to this project, follow these guidelines:

## Project Structure

- `/plugins/*` - Individual WordPress plugin folders
- `/plugins/*/tests` - PHPUnit tests for each plugin
- `/bin` - Custom CLI commands and scripts
- `/tools` - Setup and configuration files for linting and testing

## Code Style Standards

### PHP
- Follow [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- Use procedural programming patterns (classes play a supporting role)
- Do NOT use namespaces (following WordPress core conventions)
- Use the most specific PHP type hints compatible with PHP 7.2+
- For filter functions, accept `mixed` union types and validate at runtime
- Use PHPStan's PHPDoc Types (level 10 compliance)
- Never render `<script>` tags directly in HTML; use WordPress script APIs (`wp_enqueue_script()`, `wp_enqueue_script_module()`, etc.)
- Favor script modules over classic scripts

### JavaScript
- Follow [WordPress JavaScript Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/javascript/)
- Use ES modules (runnable without build step)
- Write plain JavaScript, not TypeScript
- Use JSDoc comments with [TypeScript types](https://www.typescriptlang.org/docs/handbook/jsdoc-supported-types.html)
- Format with Prettier using `wp-prettier` with `--paren-spacing` option

### HTML
- Follow [WordPress HTML Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/html/)
- Do NOT self-close void tags (use `<br>` not `<br />`)

### Documentation
- Use `@since n.e.x.t` for new code (replaced at release time)
- Every file, function, class, method, constant, and global variable needs a docblock with `@since` tag
- Follow [WordPress inline documentation standards](https://developer.wordpress.org/coding-standards/inline-documentation-standards/)

### Formatting
- Use tabs for indentation (see `.editorconfig`)

## Development Commands

### Setup
```bash
npm install
composer install
npm run build
```

### Building
- Build JS/CSS assets: `npm run build`
- Build all plugins: `npm run build-plugins`
- Build specific plugin: `npm run build:plugin:<plugin-slug>`
- Build ZIP files: `npm run build-plugins:zip`

### Linting & Static Analysis
- Lint JavaScript: `npm run lint-js`
- Lint PHP: `npm run lint-php`
- Format JavaScript: `npm run format-js`
- Format PHP: `npm run format-php`
- PHPStan: `npm run phpstan`
- TypeScript checking: `npm run tsc`

### Testing
- PHPUnit tests: `npm run test-php`
- PHPUnit multisite: `npm run test-php-multisite`
- E2E tests: `npm run test-e2e`

### Local Environment
- Uses `@wordpress/env` (default: http://localhost:8888)
- Start: `npm run wp-env start`
- Stop: `npm run wp-env stop`
- Check status: `npm run wp-env status`

## Key Requirements

- Minimum WordPress version: 6.5
- Minimum PHP version: 7.2
- All contributions released under GPLv2+ license
- Disclose AI tool usage in pull requests

## Additional Resources

For comprehensive documentation, see:
- [AGENTS.md](../AGENTS.md) - Detailed project documentation for AI agents
- [Performance Lab Handbook](https://make.wordpress.org/performance/handbook/performance-lab/)
- [CONTRIBUTING.md](../CONTRIBUTING.md)
