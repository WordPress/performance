# Performance Lab
![Performance Lab plugin banner with icon](https://user-images.githubusercontent.com/3531426/159084476-af352db4-192e-4927-a383-7f76bb3641df.png)

Monorepo for the [WordPress Performance Group](https://make.wordpress.org/core/tag/performance/), primarily for the Performance Lab plugin, which is a collection of standalone performance modules.

[Learn more about the Performance Lab plugin.](https://make.wordpress.org/core/2022/03/07/the-performance-lab-plugin-has-been-released/)

## Useful commands

In order to run the following commands, you need to have [Node.js](https://nodejs.org) (including `npm`) and [Docker](https://www.docker.com) installed, and Docker needs to be up and running. The Docker configuration used relies on the [`@wordpress/env` package](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/).

* `npm install`: Installs local development dependencies.
* `npm run wp-env start`: Starts the local development environment.
* `npm run wp-env stop`: Stops the local development environment.
* `npm run lint-php`: Lints all PHP code.
* `npm run format-php`: Formats all PHP code.
* `npm run test-php`: Runs PHPUnit tests for all PHP code.
* `npm run test-php-multisite`: Runs PHPUnit tests in multisite for all PHP code.

## Documentation

[See the `/docs` folder for documentation.](https://github.com/WordPress/performance/blob/trunk/docs/README.md)
