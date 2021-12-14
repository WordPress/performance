# Start contributing to the Performance plugin
This guide focuses on how to contribute to the Performance plugin, from setting up the development environment to creating pull requests.

## Prerequisites
- [Node.js](https://nodejs.org)
- [Docker](https://www.docker.com/products/docker-desktop)
- [Git](https://git-scm.com)

## Setup the development environment

### Clone the repository
Start by cloning the repository into your local machine. To do this, run the following command in your terminal:
`git clone https://github.com/WordPress/performance.git`

### Install the local development dependencies
To install the local development dependencies, run the following command in your terminal:
`npm install`

### Start the local WordPress environment
The Performance plugin uses Docker and [the wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env) to start a local WordPress environment.

First, make sure Docker is installed, up and running on your machine. Then, run `npm run wp-env start` to start the WordPress environment. The WordPress development site will be available at `http://localhost:8080`.

### Stop the local WordPress environment
The following command will stop the WordPress environment:
`npm run wp-env stop`

## Open an issue
Opening an issue is the first step to contributing to the Performance plugin. This allows to discuss, review or validate an idea before implementation. Opening an issue is not strict requirement, but it is highly recommended in most cases, unless the change you are trying to make is really minor or is already covered by an existing issue.

Issues should be labeled to facilitate browsing and filtering. Here are some common labels: `[Type] Feature`, `[Type] Discussion`, `Needs Decision`, `[Focus] Images`. The full list of labels can be found [here](https://github.com/WordPress/performance/labels).

## Create a pull request
A pull request must be created to submit changes to the Performance plugin. Pull requests should refer to an issue in the [repository issue tracker](https://github.com/WordPress/performance/issues).

For better triaging, it is recommended that each pull request receive a `[Type] xyz`, `[Focus] xyz` or `[Infrastructure]` labels.

## Coding standards
In general, all code must follow the [WordPress Coding Standards and best practices](https://developer.wordpress.org/coding-standards/). For a complete documentation about Performance plugin modules specifications, read this [documentation](./Writing-a-module.md).

### WordPress and PHP compatibility
All code in the Performance plugin must follow theses requirements:
- **WordPress**: the latest release is the minimum required version. Right now, the plugin is compatible with WordPress 5.8.
- **PHP**: always match the latest WordPress version. The minimum required version right now is 5.6.

### Linting and formatting
After adding new code to the repository, make sure to run this command to lint and format PHP code:
```
npm run lint-php
npms run format-php
```

## PHPUnit tests
To execute PHPUnit tests, run the `npm run test-php` in your terminal.

And to execute the tests in multisite, run:
`npm run test-php-multisite`
