const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

/**
 * It takes a file name and a file path, and returns an object that can be used to create a webpack configuration
 * The compiled file will be emitted into the same path as the source inside the build directory
 *
 * @param {string} fileName - The name of the file you want to create.
 * @param {string} filePath - The path to the file.
 * @return {Object} - An object with the following properties:
 * 	name: The name of the input file.
 * 	entry: The path to the input file.
 * 	output: An object with the following properties:
 *    filename: The name of output file.
 * 		path: The path to the output file.
 *
 * @example
 * // add to webpack the script "modules/images/webp-uploads/fallback.js"
 * // and emit the build into "modules/images/webp-uploads/build/fallback.js"
 * addModule( 'fallback', 'modules/images/webp-uploads/' );
 */
const addModule = ( fileName, filePath ) => {
	return {
		...defaultConfig,
		name: fileName,
		entry: path.resolve( __dirname, filePath + fileName ),
		output: {
			path: path.resolve( __dirname, filePath + 'dist/' ),
			filename: fileName,
		},
	};
};

const webpFallback = addModule( 'fallback.js', 'modules/images/webp-uploads/' );

module.exports = [ webpFallback ];
