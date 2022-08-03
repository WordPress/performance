/**
 * External dependencies
 */
const chalk = require( 'chalk' );

const title = chalk.bold;
const error = chalk.bold.red;
const warning = chalk.bold.keyword( 'orange' );
const success = chalk.bold.green;

const log = console.log; // eslint-disable-line no-console

module.exports = {
	log,
	formats: {
		title,
		error,
		warning,
		success,
	},
};
