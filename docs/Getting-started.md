[Back to overview](./README.md)

# Start contributing to the Performance Lab plugin
This guide focuses on how to contribute to the Performance Lab plugin, from setting up the development environment to creating pull requests.

## Prerequisites
- [Node.js](https://nodejs.org)
- [Docker](https://www.docker.com/products/docker-desktop)
- [Git](https://git-scm.com)

## Setup the development environment

### Clone the repository
Start by cloning the repository into your local machine. To do this, run the following command in your terminal:
```
git clone https://github.com/WordPress/performance.git
```

### Install the local development dependencies
To install the local development dependencies, run the following command in your terminal:
```
npm install
```

### Start the local WordPress environment
The Performance Lab plugin uses Docker and [the wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env) to start a local WordPress environment.

First, make sure Docker is installed, up and running on your machine. Then, run `npm run wp-env start` to start the WordPress environment. The WordPress development site will be available at `http://localhost:8080`.

### Stop the local WordPress environment
The following command will stop the WordPress environment:
```
npm run wp-env stop
```

## Opening and working on issues
We use GitHub Projects as well as a few different sets of labels to ensure that issues are properly tracked and able to be easily managed by contributors. For details, please refer to our [GitHub workflow documentation](./Github-workflow.md).

## Coding standards
In general, all code must follow the [WordPress Coding Standards and best practices](https://developer.wordpress.org/coding-standards/). For a complete documentation about Performance Lab plugin modules specifications, read this [documentation](./Writing-a-module.md).

### WordPress and PHP compatibility
All code in the Performance Lab plugin must follow theses requirements:
- **WordPress**: the latest release is the minimum required version. Right now, the plugin is compatible with WordPress 5.8.
- **PHP**: always match the latest WordPress version. The minimum required version right now is 5.6.

### Linting and formatting
After adding new code to the repository, make sure to run this command to lint and format PHP code:
```
npm run lint-php
npms run format-php
```

## PHPUnit tests
To execute PHPUnit tests, run this in your terminal:
```
npm run test-php
```

And to execute the tests in multisite, run:
```
npm run test-php-multisite
```

## Other ways to contribute
Not a developer? You can still help! We’re always looking for people to assist with support on our WordPress.org support forum. Please reach out in the #performance Slack channel for more details.
